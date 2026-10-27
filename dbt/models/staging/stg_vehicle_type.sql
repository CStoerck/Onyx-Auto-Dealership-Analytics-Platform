select cast(typename as varchar) as vehicle_type 
from {{ source('mysql_load', 'vehicle_type') }}