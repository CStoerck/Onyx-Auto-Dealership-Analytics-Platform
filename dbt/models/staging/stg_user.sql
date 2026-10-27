select 
    cast(username as varchar) as username, 
    cast(firstname as varchar) as first_name, 
    cast(lastname as varchar) as last_name, 
    concat(cast(firstname as varchar), ' ', cast(lastname as varchar)) as employee_display_name 
from {{ source('mysql_load', 'user') }}