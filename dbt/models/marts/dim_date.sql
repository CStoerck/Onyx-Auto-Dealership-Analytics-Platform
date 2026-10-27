with date_spine as ( 
    {{ dbt_utils.date_spine( 
        datepart="day", 
        start_date="to_date('2020-01-01')", 
        end_date="dateadd(year, 2, current_date())" 
    ) }} 
) 
select 
    to_number(to_char(date_day, 'YYYYMMDD')) as date_key, 
    cast(date_day as date) as date, 
    year(date_day) as year, 
    quarter(date_day) as quarter, 
    month(date_day) as month_number, 
    monthname(date_day) as month_name, 
    to_char(date_day, 'YYYY-MM') as year_month, 
    year(date_day) * 100 + month(date_day) as year_month_sort, 
    weekofyear(date_day) as week_of_year, 
    dayofweek(date_day) as day_of_week_number, 
    dayname(date_day) as day_of_week_name, 
    iff(date_day = last_day(date_day), true, false) as is_month_end 
from date_spine