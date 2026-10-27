select 
    cast(vin as varchar) as vin, 
    cast(modelname as varchar) as model_name, 
    cast(modelyear as number) as model_year, 
    cast(fueltype as varchar) as fuel_type, 
    cast(condition as varchar) as vehicle_condition, 
    cast(horsepower as number) as horsepower, 
    cast(drivetrain as varchar) as drivetrain, 
    nullif(trim(cast(notes as varchar)), '') as notes, 
    cast(purchprice as number(12, 2)) as purchase_price, 
    cast(purchdate as date) as purchase_date, 
    cast(typename as varchar) as vehicle_type, 
    cast(mfgname as varchar) as manufacturer, 
    cast(purchaser_customerid as number) as purchaser_customer_id, 
    cast(acqspecialist_username as varchar) as acquisition_specialist_username 
from {{ source('mysql_load', 'vehicle') }}