select 
    c.customer_id as seller_customer_id, 
    c.display_name as seller_name, 
    c.customer_type as seller_type, 
    count(distinct v.vin) as total_vehicles_purchased, 
    round(avg(v.purchase_price), 2) as avg_purchase_price, 
    round(coalesce(sum(p.total_parts_quantity), 0) / nullif(count(distinct v.vin), 0), 2) as avg_parts_quantity_per_vehicle, 
    round(coalesce(sum(p.total_parts_cost_all_statuses), 0) / nullif(count(distinct v.vin), 0), 2) as avg_parts_cost_per_vehicle, 
    iff( 
        coalesce(sum(p.total_parts_quantity), 0) / nullif(count(distinct v.vin), 0) >= 5 
        or coalesce(sum(p.total_parts_cost_all_statuses), 0) / nullif(count(distinct v.vin), 0) >= 500, 
        true, 
        false 
    ) as seller_risk_flag 
from {{ ref('stg_vehicle') }} v 
inner join {{ ref('dim_customer') }} c on v.purchaser_customer_id = c.customer_id 
left join {{ ref('int_vehicle_parts_costs') }} p on v.vin = p.vin 
group by c.customer_id, c.display_name, c.customer_type