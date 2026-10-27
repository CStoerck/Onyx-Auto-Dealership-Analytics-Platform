select 
    cast(vin as varchar) as vin, 
    cast(buyer_customerid as number) as buyer_customer_id, 
    cast(salesagent_username as varchar) as sales_agent_username, 
    cast(saledate as date) as sale_date 
from {{ source('mysql_load', 'sale') }}