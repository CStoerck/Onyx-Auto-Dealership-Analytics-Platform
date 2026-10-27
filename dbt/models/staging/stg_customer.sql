select 
    cast(customerid as number) as customer_id, 
    cast(phone as varchar) as phone, 
    nullif(trim(cast(email as varchar)), '') as email, 
    cast(streetaddress as varchar) as street_address, 
    cast(city as varchar) as city, 
    cast(state as varchar) as state, 
    cast(postalcode as varchar) as postal_code 
from {{ source('mysql_load', 'customer') }}