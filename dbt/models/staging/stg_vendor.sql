select 
    cast(vendorname as varchar) as vendor_name, 
    cast(phone as varchar) as phone, 
    cast(streetaddress as varchar) as street_address, 
    cast(city as varchar) as city, 
    cast(state as varchar) as state, 
    cast(postalcode as varchar) as postal_code 
from {{ source('mysql_load', 'vendor') }}