select 
    cast(ssn as varchar) as ssn, 
    cast(firstname as varchar) as first_name, 
    cast(lastname as varchar) as last_name, 
    cast(customerid as number) as customer_id 
from {{ source('mysql_load', 'individual') }}