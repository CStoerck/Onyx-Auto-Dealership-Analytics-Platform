select cast(username as varchar) as username 
from {{ source('mysql_load', 'acquisition_specialist') }}