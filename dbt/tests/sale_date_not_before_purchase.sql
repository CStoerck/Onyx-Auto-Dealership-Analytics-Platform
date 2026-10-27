-- A vehicle cannot be sold before it was purchased.
select s.vin, s.sale_date, v.purch_date
from {{ ref('stg_sales') }} s
join {{ ref('stg_vehicles') }} v using (vin)
where s.sale_date < v.purch_date
