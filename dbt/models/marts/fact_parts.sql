select 
    p.part_line_key, 
    p.vin, 
    p.order_num, 
    po.parts_order_key, 
    po.vendor_name, 
    p.part_number, 
    p.description, 
    p.unit_price, 
    p.quantity, 
    p.extended_cost, 
    p.status, 
    iff(p.status = 'Ordered', true, false) as is_ordered, 
    iff(p.status = 'Received', true, false) as is_received, 
    iff(p.status = 'Installed', true, false) as is_installed, 
    iff(p.status <> 'Installed', true, false) as is_pending 
from {{ ref('stg_part') }} p 
left join {{ ref('stg_parts_order') }} po 
    on p.vin = po.vin 
   and p.order_num = po.order_num