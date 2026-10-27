select cast(mfgname as varchar) as manufacturer 
from {{ source('mysql_load', 'manufacturer') }}