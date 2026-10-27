select 
    v.vin, 
    v.purchaser_customer_id as seller_customer_id, 
    v.acquisition_specialist_username, 
    to_number(to_char(v.purchase_date, 'YYYYMMDD')) as purchase_date_key, 
    to_number(to_char(s.sale_date, 'YYYYMMDD')) as sale_date_key, 
    s.buyer_customer_id, 
    s.sales_agent_username, 
    f.purchase_price, 
    f.total_parts_cost, 
    f.installed_parts_cost, 
    f.pending_parts_cost, 
    f.calculated_sale_price, 
    f.net_income, 
    f.days_in_inventory, 
    f.current_age_days, 
    f.gross_margin_pct, 
    f.is_sold, 
    f.is_unsold, 
    f.has_pending_parts, 
    f.is_available_for_sale, 
    f.inventory_status 
from {{ ref('stg_vehicle') }} v 
left join {{ ref('stg_sale') }} s on v.vin = s.vin 
left join {{ ref('int_vehicle_financials') }} f on v.vin = f.vin