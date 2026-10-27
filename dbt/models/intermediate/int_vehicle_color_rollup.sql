select 
    vin, 
    listagg(distinct color, ', ') within group (order by color) as colors_csv, 
    count(distinct color) as color_count 
from {{ ref('stg_vehicle_color') }} 
group by vin