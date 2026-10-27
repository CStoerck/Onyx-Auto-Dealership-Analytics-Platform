select 
    {{ dealership_surrogate_key(['vin', 'color']) }} as vehicle_color_key, 
    cast(vin as varchar) as vin, 
    cast(color as varchar) as color 
from {{ source('mysql_load', 'vehicle_color') }}