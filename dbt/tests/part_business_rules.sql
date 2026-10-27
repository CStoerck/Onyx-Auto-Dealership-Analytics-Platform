-- Part prices and quantities must be positive.
select *
from {{ ref('stg_parts') }}
where unit_price <= 0 or quantity <= 0
