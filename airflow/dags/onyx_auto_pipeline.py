from datetime import datetime, timedelta
from airflow import DAG
from airflow.operators.python import PythonOperator
from airflow.operators.empty import EmptyOperator
from airflow.operators.bash import BashOperator
from airflow.models import Variable
import requests
import time
import msal


# 1. Configuration Variables

AIRBYTE_BASE_URL = "http://host.docker.internal:8000/api/v1"
AIRBYTE_CONNECTION_ID = "1d53fdc7-22e8-412b-a258-6945049e8bdd"

AIRBYTE_USERNAME = Variable.get("AIRBYTE_USERNAME")
AIRBYTE_PASSWORD = Variable.get("AIRBYTE_PASSWORD")

DBT_PROJECT_DIR = "/opt/airflow/dbt/dealership_analytics"
DBT_PROFILES_DIR = "/opt/airflow/dbt"

TEAMS_WEBHOOK_URL = Variable.get("TEAMS_WEBHOOK_URL")

POWER_BI_TENANT_ID = Variable.get("POWER_BI_TENANT_ID")
POWER_BI_CLIENT_ID = Variable.get("POWER_BI_CLIENT_ID")
POWER_BI_CLIENT_SECRET = Variable.get("POWER_BI_CLIENT_SECRET")

POWER_BI_WORKSPACE_ID = Variable.get("POWER_BI_WORKSPACE_ID")
POWER_BI_DATASET_ID = Variable.get("POWER_BI_DATASET_ID")


# 2. Alerts

def send_failure_alert(context):

    if TEAMS_WEBHOOK_URL == "YOUR_TEAMS_WEBHOOK_URL":
        print("No Teams webhook URL has been configured. Skipping failure alert.")
        return

    task_instance = context.get('task_instance')
    dag = context.get('dag')
    exception = context.get('exception')
    run_id = context.get('run_id')

    dag_id = dag.dag_id if dag else "unknown_dag"
    task_id = task_instance.task_id if task_instance else "unknown_task"

    message = {
        "@type": "MessageCard",
        "@context": "https://schema.org/extensions",
        "summary": "Onyx Auto ELT pipeline failed",
        "themeColor": "D13438",
        "title": "Onyx Auto ELT pipeline failed",
        "sections": [
            {
                "activityTitle": "A task failed during the automated data pipeline run.",
                "facts": [
                    {
                        "name": "Pipeline",
                        "value": dag_id
                    },
                    {
                        "name": "Failed task",
                        "value": task_id
                    },
                    {
                        "name": "Environment",
                        "value": "local/dev"
                    },
                    {
                        "name": "Impact",
                        "value": "Analytics marts may not be fully refreshed. Power BI may show the last successful refresh."
                    },
                    {
                        "name": "Action",
                        "value": "Review the failed Airflow task logs, resolve the issue, and rerun the DAG."
                    },
                    {
                        "name": "Run ID",
                        "value": str(run_id)
                    },
                    {
                        "name": "Error",
                        "value": str(exception)
                    }
                ],
                "markdown": True
            }
        ]
    }

    print("Sending failure alert to Microsoft Teams...")
    print(f"Alert payload: {message}")

    try:
        response = requests.post(
            TEAMS_WEBHOOK_URL,
            json=message,
            timeout=30
        )

        print(f"Teams alert response status code: {response.status_code}")
        print(f"Teams alert response text: {response.text}")

        if response.status_code not in [200, 202]:
            print(
                f"Teams alert did not return a success status code. "
                f"Status code: {response.status_code}. Response: {response.text}"
            )

    except Exception as alert_error:
        print(f"Failure alert could not be sent: {alert_error}")


# 3. Define standard default arguments
# This ensures consistent retry and alert behavior across all tasks.

default_args = {
    'owner': 'data_engineering_team',
    'depends_on_past': False,
    'email_on_failure': False,
    'email_on_retry': False,
    'retries': 0,  # Temporarily disabled while debugging
    'retry_delay': timedelta(minutes=5),
    'on_failure_callback': send_failure_alert,
}


# 4. Define Python functions
# Keeping the business logic separate from the DAG definition improves readability.

def trigger_airbyte_sync(**kwargs):
    """
    Triggers an Airbyte sync to move data from MySQL to Snowflake.

    Airbyte handles the CDC ingestion process. Airflow is only responsible
    for triggering the sync and orchestrating the pipeline order.
    """

    print("Starting Airbyte sync...")

    url = f"{AIRBYTE_BASE_URL}/connections/sync"

    payload = {
        "connectionId": AIRBYTE_CONNECTION_ID
    }

    headers = {
        "Content-Type": "application/json",
        "Accept": "application/json"
    }

    print(f"Sending request to Airbyte URL: {url}")
    print(f"Using Airbyte connection ID: {AIRBYTE_CONNECTION_ID}")

    response = requests.post(
        url,
        json=payload,
        headers=headers,
        auth=(AIRBYTE_USERNAME, AIRBYTE_PASSWORD),
        timeout=60
    )

    print(f"Airbyte response status code: {response.status_code}")
    print(f"Airbyte response text: {response.text}")

    if response.status_code != 200:
        raise Exception(f"Airbyte sync failed to start: {response.text}")

    response_data = response.json()
    job_id = response_data["job"]["id"]

    print(f"Airbyte sync triggered successfully. Job ID: {job_id}")

    return job_id


def wait_for_airbyte_sync(**kwargs):
    """
    Waits for the Airbyte sync job to finish.

    This prevents downstream tasks, such as dbt transformations, from running
    before the latest raw data has finished loading into Snowflake.
    """

    print("Starting Airbyte sync status check...")

    task_instance = kwargs['ti']
    job_id = task_instance.xcom_pull(task_ids='trigger_airbyte_sync')

    if job_id is None:
        raise Exception("No Airbyte job ID was found from the trigger task.")

    url = f"{AIRBYTE_BASE_URL}/jobs/get"

    payload = {
        "id": job_id
    }

    headers = {
        "Content-Type": "application/json",
        "Accept": "application/json"
    }

    print(f"Waiting for Airbyte job ID: {job_id}")

    while True:
        response = requests.post(
            url,
            json=payload,
            headers=headers,
            auth=(AIRBYTE_USERNAME, AIRBYTE_PASSWORD),
            timeout=60
        )

        print(f"Airbyte job status response code: {response.status_code}")
        print(f"Airbyte job status response text: {response.text}")

        if response.status_code != 200:
            raise Exception(f"Could not check Airbyte job status: {response.text}")

        response_data = response.json()
        job_status = response_data["job"]["status"]

        print(f"Current Airbyte job status: {job_status}")

        if job_status == "succeeded":
            print("Airbyte sync completed successfully.")
            break

        if job_status in ["failed", "cancelled", "incomplete"]:
            raise Exception(f"Airbyte sync ended with status: {job_status}")

        print("Airbyte sync is still running. Waiting 30 seconds before checking again.")
        time.sleep(30)


def get_power_bi_access_token():
    """
    Gets an access token from Microsoft Entra ID for the Power BI REST API.

    This uses the service principal credentials stored in Airflow Variables.
    Airflow uses this token to authenticate with Power BI and trigger the
    semantic model refresh after dbt finishes successfully.
    """

    print("Requesting Power BI access token...")

    authority_url = f"https://login.microsoftonline.com/{POWER_BI_TENANT_ID}"

    app = msal.ConfidentialClientApplication(
        client_id=POWER_BI_CLIENT_ID,
        client_credential=POWER_BI_CLIENT_SECRET,
        authority=authority_url
    )

    scopes = ["https://analysis.windows.net/powerbi/api/.default"]

    token_response = app.acquire_token_for_client(scopes=scopes)

    if "access_token" not in token_response:
        raise Exception(f"Could not acquire Power BI access token: {token_response}")

    print("Power BI access token acquired successfully.")

    return token_response["access_token"]


def refresh_power_bi_semantic_model(**kwargs):
    """
    Triggers a Power BI semantic model refresh.

    This task runs after dbt build finishes successfully. The purpose is to
    refresh the Power BI consumption layer only after the Snowflake mart models
    have been rebuilt.
    """

    print("Starting Power BI semantic model refresh...")

    access_token = get_power_bi_access_token()

    url = (
        f"https://api.powerbi.com/v1.0/myorg/groups/"
        f"{POWER_BI_WORKSPACE_ID}/datasets/{POWER_BI_DATASET_ID}/refreshes"
    )

    headers = {
        "Authorization": f"Bearer {access_token}",
        "Content-Type": "application/json"
    }

    print(f"Sending refresh request to Power BI URL: {url}")
    print(f"Using Power BI workspace ID: {POWER_BI_WORKSPACE_ID}")
    print(f"Using Power BI semantic model/dataset ID: {POWER_BI_DATASET_ID}")

    response = requests.post(
        url,
        headers=headers,
        timeout=60
    )

    print(f"Power BI refresh response status code: {response.status_code}")
    print(f"Power BI refresh response text: {response.text}")

    if response.status_code != 202:
        raise Exception(
            f"Power BI semantic model refresh failed to start. "
            f"Status code: {response.status_code}. Response: {response.text}"
        )

    print("Power BI semantic model refresh was triggered successfully.")


# 5. Instantiate the DAG
# This DAG runs at 3:00 AM every day and orchestrates Airbyte CDC plus dbt transformations.

with DAG(
    dag_id='onyx_auto_pipeline',
    default_args=default_args,
    description='Runs the Onyx Auto ELT pipeline from Airbyte CDC to dbt transformations.',
    schedule_interval='0 3 * * *',
    start_date=datetime(2026, 5, 20),
    catchup=False,
    max_active_runs=1,
    tags=['onyx-auto', 'airbyte', 'snowflake', 'dbt'],
) as dag:

    # 6. Define Tasks

    start_pipeline = EmptyOperator(
        task_id='start_pipeline'
    )

    trigger_sync = PythonOperator(
        task_id='trigger_airbyte_sync',
        python_callable=trigger_airbyte_sync,
        provide_context=True
    )

    wait_for_sync = PythonOperator(
        task_id='wait_for_airbyte_sync',
        python_callable=wait_for_airbyte_sync,
        provide_context=True
    )

    run_dbt_build = BashOperator(
        task_id='run_dbt_build',
        bash_command=f"""
        dbt build \
          --project-dir {DBT_PROJECT_DIR} \
          --profiles-dir {DBT_PROFILES_DIR} \
          --exclude stg_part+ stg_parts_order+ my_first_dbt_model my_second_dbt_model agg_price_per_condition
        """
    )

    refresh_semantic_model = PythonOperator(
        task_id='refresh_power_bi_semantic_model',
        python_callable=refresh_power_bi_semantic_model,
        provide_context=True
    )

    end_pipeline = EmptyOperator(
        task_id='end_pipeline'
    )

    # 7. Define Dependencies
    # This makes the full data pipeline order clear in the Airflow UI.

    start_pipeline >> trigger_sync >> wait_for_sync >> run_dbt_build >> refresh_semantic_model >> end_pipeline
