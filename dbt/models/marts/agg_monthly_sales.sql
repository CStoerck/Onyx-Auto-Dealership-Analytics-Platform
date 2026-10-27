select 
    year(d.date) as sale_year, 
    month(d.date) as sale_month, 
    d.year_month, 
    d.year_month_sort, 
    count(distinct s.vin) as total_vehicles_sold, 
    round(sum(s.gross_sales_income), 2) as gross_sales_income, 
    round(sum(s.net_income), 2) as total_net_income, 
    round(avg(s.gross_sales_income), 2) as avg_sale_price, 
    round(avg(s.net_income), 2) as avg_net_income_per_vehicle, 
    round(sum(s.net_income) / nullif(sum(s.gross_sales_income), 0), 4) as gross_margin_pct 
from {{ ref('fact_sales') }} s 
inner join {{ ref('dim_date') }} d on s.sale_date_key = d.date_key 
group by sale_year, sale_month, d.year_month, d.year_month_sort