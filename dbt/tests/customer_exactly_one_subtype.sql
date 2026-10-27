-- Each customer should resolve to exactly one subtype: individual or business.
select c.customer_id
from {{ ref('stg_customers') }} c
left join {{ ref('stg_individuals') }} i using (customer_id)
left join {{ ref('stg_businesses') }} b using (customer_id)
where (i.customer_id is null and b.customer_id is null)
   or (i.customer_id is not null and b.customer_id is not null)
