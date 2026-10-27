select 
    s.vin, 
    s.sale_date, 
    to_number(to_char(s.sale_date, 'YYYYMMDD')) as sale_date_key, 
    s.buyer_customer_id, 
    s.sales_agent_username, 
    v.purchaser_customer_id as seller_customer_id, 
    v.acquisition_specialist_username, 
    v.vehicle_type, 
    v.manufacturer, 
    v.model_name, 
    v.model_year, 
    v.fuel_type, 
    v.vehicle_condition, 
    v.drivetrain, 
    v.purchase_price, 
    v.purchase_date, 
    f.installed_parts_cost, 
    f.total_parts_cost, 
    f.calculated_sale_price as gross_sales_income, 
    f.net_income, 
    f.days_in_inventory, 
    f.gross_margin_pct 
from {{ ref('stg_sale') }} s 
inner join {{ ref('stg_vehicle') }} v on s.vin = v.vin 
inner join {{ ref('int_vehicle_financials') }} f on s.vin = f.vin