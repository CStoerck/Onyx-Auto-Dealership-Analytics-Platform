with vehicle as ( 
    select * from {{ ref('stg_vehicle') }} 
), sale as ( 
    select * from {{ ref('stg_sale') }} 
), parts as ( 
    select * from {{ ref('int_vehicle_parts_costs') }} 
) 
select 
    v.vin, 
    s.sale_date, 
    iff(s.vin is not null, true, false) as is_sold, 
    iff(s.vin is null, true, false) as is_unsold, 
    coalesce(p.has_pending_parts, false) as has_pending_parts, 
    iff(s.vin is null and coalesce(p.has_pending_parts, false) = false, true, false) as is_available_for_sale, 
    case 
        when s.vin is not null then 'Sold' 
        when coalesce(p.has_pending_parts, false) then 'Pending Parts' 
        else 'Available' 
    end as inventory_status 
from vehicle v 
left join sale s on v.vin = s.vin 
left join parts p on v.vin = p.vin