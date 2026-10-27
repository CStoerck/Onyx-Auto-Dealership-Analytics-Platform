-- Portfolio security control: raw credential-like values should not reach analytics models.
{{ config(severity='warn') }}
select username
from {{ ref('stg_users') }}
where password is not null
