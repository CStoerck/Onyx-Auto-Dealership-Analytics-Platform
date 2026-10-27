select 
    v.vin, 
    v.vehicle_type, 
    v.manufacturer, 
    v.model_name, 
    v.model_year, 
    v.fuel_type, 
    v.vehicle_condition, 
    v.horsepower, 
    v.drivetrain, 
    v.notes, 
    coalesce(c.colors_csv, 'Unknown') as colors_csv, 
    coalesce(c.color_count, 0) as color_count, 
    v.purchase_price, 
    v.purchase_date, 
    to_number(to_char(v.purchase_date, 'YYYYMMDD')) as purchase_date_key, 
    v.purchaser_customer_id as seller_customer_id, 
    v.acquisition_specialist_username 
from {{ ref('stg_vehicle') }} v 
left join {{ ref('int_vehicle_color_rollup') }} c on v.vin = c.vin