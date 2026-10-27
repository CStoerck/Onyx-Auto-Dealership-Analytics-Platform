select 
    u.username, 
    u.first_name, 
    u.last_name, 
    u.employee_display_name, 
    iff(a.username is not null, true, false) as is_acquisition_specialist, 
    iff(s.username is not null, true, false) as is_sales_agent, 
    iff(o.username is not null, true, false) as is_operating_manager, 
    iff(a.username is not null and s.username is not null and o.username is not null, true, false) as is_owner_candidate 
from {{ ref('stg_user') }} u 
left join {{ ref('stg_acquisition_specialist') }} a on u.username = a.username 
left join {{ ref('stg_sales_agent') }} s on u.username = s.username 
left join {{ ref('stg_operating_manager') }} o on u.username = o.username