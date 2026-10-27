select 
    vt.vehicle_type, 
    count(s.vin) as vehicles_sold, 
    iff(count(s.vin) > 0, true, false) as has_sales_history, 
    round(avg(case when s.vin is not null then datediff('day', v.purchase_date, s.sale_date) + 1 end), 2) as avg_days_in_inventory, 
    case 
        when count(s.vin) = 0 then 'N/A' 
        else to_varchar(round(avg(datediff('day', v.purchase_date, s.sale_date) + 1), 2)) 
    end as avg_days_in_inventory_display 
from {{ ref('stg_vehicle_type') }} vt 
left join {{ ref('stg_vehicle') }} v on vt.vehicle_type = v.vehicle_type 
left join {{ ref('stg_sale') }} s on v.vin = s.vin 
group by vt.vehicle_type