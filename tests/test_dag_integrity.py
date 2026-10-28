import os
import pytest
from airflow.models import DagBag

# Mock the 8 Airflow Variables so onyx_auto_pipeline.py loads offline in CI
MOCK_AIRFLOW_VARS = {
    "AIRFLOW_VAR_AIRBYTE_USERNAME": "ci_test_user",
    "AIRFLOW_VAR_AIRBYTE_PASSWORD": "ci_test_password",
    "AIRFLOW_VAR_TEAMS_WEBHOOK_URL": "YOUR_TEAMS_WEBHOOK_URL",
    "AIRFLOW_VAR_POWER_BI_TENANT_ID": "00000000-0000-0000-0000-000000000000",
    "AIRFLOW_VAR_POWER_BI_CLIENT_ID": "00000000-0000-0000-0000-000000000000",
    "AIRFLOW_VAR_POWER_BI_CLIENT_SECRET": "ci_test_secret",
    "AIRFLOW_VAR_POWER_BI_WORKSPACE_ID": "00000000-0000-0000-0000-000000000000",
    "AIRFLOW_VAR_POWER_BI_DATASET_ID": "00000000-0000-0000-0000-000000000000",
}

for key, value in MOCK_AIRFLOW_VARS.items():
    os.environ.setdefault(key, value)

DAG_PATH = os.path.join(os.path.dirname(__file__), "../airflow/dags")


@pytest.fixture(scope="session")
def dag_bag():
    return DagBag(dag_folder=DAG_PATH, include_examples=False)


def test_no_import_errors(dag_bag):
    """Verify that all Airflow DAGs parse with zero syntax or import errors."""
    assert len(dag_bag.import_errors) == 0, f"DAG import errors found: {dag_bag.import_errors}"


def test_onyx_auto_pipeline_tasks_and_order(dag_bag):
    """Verify onyx_auto_pipeline tasks and linear dependency chain."""
    assert "onyx_auto_pipeline" in dag_bag.dags
    dag = dag_bag.get_dag("onyx_auto_pipeline")

    assert "trigger_airbyte_sync" in dag.get_task("start_pipeline").downstream_task_ids
    assert "wait_for_airbyte_sync" in dag.get_task("trigger_airbyte_sync").downstream_task_ids
    assert "run_dbt_build" in dag.get_task("wait_for_airbyte_sync").downstream_task_ids
    assert "refresh_power_bi_semantic_model" in dag.get_task("run_dbt_build").downstream_task_ids
    assert "end_pipeline" in dag.get_task("refresh_power_bi_semantic_model").downstream_task_ids
