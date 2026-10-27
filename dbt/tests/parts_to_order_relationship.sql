-- Composite FK check from parts to parts orders.
select p.vin, p.order_num, p.part_number
from {{ ref('stg_parts') }} p
left join {{ ref('stg_parts_orders') }} o
  on p.vin = o.vin and p.order_num = o.order_num
where o.vin is null
