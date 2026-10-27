with vehicle as ( 
    select * from {{ ref('stg_vehicle') }} 
), sale as ( 
    select * from {{ ref('stg_sale') }} 
), parts as ( 
    select * from {{ ref('int_vehicle_parts_costs') }} 
), status as ( 
    select * from {{ ref('int_vehicle_status') }} 
) 
select 
    v.vin, 
    v.purchase_price, 
    v.purchase_date, 
    s.sale_date, 
    coalesce(p.total_parts_cost_all_statuses, 0) as total_parts_cost, 
    coalesce(p.installed_parts_cost, 0) as installed_parts_cost, 
    coalesce(p.pending_parts_cost, 0) as pending_parts_cost, 
    round(v.purchase_price * 1.25 + coalesce(p.installed_parts_cost, 0) * 1.10, 2) as calculated_sale_price, 
    case 
        when s.vin is not null then round((v.purchase_price * 1.25 + coalesce(p.installed_parts_cost, 0) * 1.10) - v.purchase_price - coalesce(p.installed_parts_cost, 0), 2) 
        else null 
    end as net_income, 
    case 
        when s.vin is not null then datediff('day', v.purchase_date, s.sale_date) + 1 
        else null 
    end as days_in_inventory, 
    case 
        when s.vin is null then datediff('day', v.purchase_date, current_date()) + 1 
        else null 
    end as current_age_days, 
    iff(s.vin is not null, round(((v.purchase_price * 1.25 + coalesce(p.installed_parts_cost, 0) * 1.10) - v.purchase_price - coalesce(p.installed_parts_cost, 0)) / nullif((v.purchase_price * 1.25 + coalesce(p.installed_parts_cost, 0) * 1.10), 0), 4), null) as gross_margin_pct, 
    status.is_sold, 
    status.is_unsold, 
    status.has_pending_parts, 
    status.is_available_for_sale, 
    status.inventory_status 
from vehicle v 
left join sale s on v.vin = s.vin 
left join parts p on v.vin = p.vin 
left join status on v.vin = status.vin