select 
    cast(taxid as varchar) as tax_id, 
    cast(businessname as varchar) as business_name, 
    cast(contactfirstname as varchar) as contact_first_name, 
    cast(contactlastname as varchar) as contact_last_name, 
    nullif(trim(cast(contacttitle as varchar)), '') as contact_title, 
    cast(customerid as number) as customer_id 
from {{ source('mysql_load', 'business') }}