select 
    {{ dealership_surrogate_key(['vin', 'ordernum', 'partnumber']) }} as part_line_key, 
    cast(vin as varchar) as vin, 
    cast(ordernum as varchar) as order_num, 
    cast(partnumber as varchar) as part_number, 
    cast(description as varchar) as description, 
    cast(unitprice as number(12, 2)) as unit_price, 
    cast(quantity as number) as quantity, 
    cast(status as varchar) as status, 
    cast(unitprice as number(12, 2)) * cast(quantity as number) as extended_cost 
from {{ source('mysql_load', 'part') }}