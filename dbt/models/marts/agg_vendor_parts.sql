select 
    v.vendor_name, 
    coalesce(sum(p.quantity), 0) as total_parts_quantity, 
    round(coalesce(sum(p.extended_cost), 0), 2) as total_parts_spend, 
    round(avg(p.unit_price), 2) as avg_unit_price, 
    count(distinct p.vin) as distinct_vins_serviced, 
    count(distinct po.parts_order_key) as distinct_orders 
from {{ ref('dim_vendor') }} v 
left join {{ ref('stg_parts_order') }} po on v.vendor_name = po.vendor_name 
left join {{ ref('stg_part') }} p on po.vin = p.vin and po.order_num = p.order_num 
group by v.vendor_name