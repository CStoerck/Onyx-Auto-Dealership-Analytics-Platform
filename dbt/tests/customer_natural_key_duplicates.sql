-- Warn-worthy duplicate customer identities introduced under different surrogate IDs.
{{ config(severity='warn') }}
with identities as (
  select customer_id, 'individual' as customer_type,
         regexp_replace(ssn, '[^0-9]', '') as natural_key
  from {{ ref('stg_individuals') }}
  union all
  select customer_id, 'business', upper(trim(tax_id))
  from {{ ref('stg_businesses') }}
)
select customer_type, natural_key
from identities
group by 1, 2
having count(distinct customer_id) > 1
