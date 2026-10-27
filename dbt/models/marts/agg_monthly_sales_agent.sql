with agent_month as ( 
    select 
        d.year_month, 
        d.year_month_sort, 
        year(d.date) as sale_year, 
        month(d.date) as sale_month, 
        s.sales_agent_username as username, 
        count(distinct s.vin) as total_vehicles_sold, 
        round(sum(s.gross_sales_income), 2) as total_sales, 
        round(sum(s.net_income), 2) as total_net_income 
    from {{ ref('fact_sales') }} s 
    inner join {{ ref('dim_date') }} d on s.sale_date_key = d.date_key 
    group by d.year_month, d.year_month_sort, sale_year, sale_month, s.sales_agent_username 
) 
select 
    {{ dealership_surrogate_key(['year_month', 'username']) }} as monthly_sales_agent_key, 
    *, 
    dense_rank() over ( 
        partition by year_month 
        order by total_vehicles_sold desc, total_sales desc 
    ) as rank_by_vehicle_count_then_sales 
from agent_month