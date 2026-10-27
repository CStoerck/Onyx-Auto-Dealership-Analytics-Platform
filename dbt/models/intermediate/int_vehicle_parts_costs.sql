select 
    v.vin, 
    coalesce(sum(p.extended_cost), 0) as total_parts_cost_all_statuses, 
    coalesce(sum(case when p.status = 'Installed' then p.extended_cost else 0 end), 0) as installed_parts_cost, 
    coalesce(sum(case when p.status <> 'Installed' then p.extended_cost else 0 end), 0) as pending_parts_cost, 
    coalesce(sum(p.quantity), 0) as total_parts_quantity, 
    coalesce(sum(case when p.status = 'Installed' then p.quantity else 0 end), 0) as installed_parts_quantity, 
    coalesce(sum(case when p.status <> 'Installed' then p.quantity else 0 end), 0) as pending_parts_quantity, 
    coalesce(sum(case when p.status = 'Ordered' then 1 else 0 end), 0) as ordered_parts_count, 
    coalesce(sum(case when p.status = 'Received' then 1 else 0 end), 0) as received_parts_count, 
    coalesce(sum(case when p.status = 'Installed' then 1 else 0 end), 0) as installed_parts_count, 
    iff(coalesce(sum(case when p.status <> 'Installed' then 1 else 0 end), 0) > 0, true, false) as has_pending_parts 
from {{ ref('stg_vehicle') }} v 
left join {{ ref('stg_part') }} p on v.vin = p.vin 
group by v.vin