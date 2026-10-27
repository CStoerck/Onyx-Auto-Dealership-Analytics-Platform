select 
    {{ dealership_surrogate_key(['vin', 'ordernum', 'vendorname']) }} as parts_order_key, 
    cast(vin as varchar) as vin, 
    cast(ordernum as varchar) as order_num, 
    cast(vendorname as varchar) as vendor_name 
from {{ source('mysql_load', 'parts_order') }}