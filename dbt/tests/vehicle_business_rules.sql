-- Fails for impossible years, nonpositive horsepower/price, or future purchase dates.
select *
from {{ ref('stg_vehicles') }}
where model_year not between 1000 and year(current_date)
   or horsepower <= 0
   or purch_price <= 0
   or purch_date > current_date
