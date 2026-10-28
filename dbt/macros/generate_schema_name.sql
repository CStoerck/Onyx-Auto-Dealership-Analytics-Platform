{% macro generate_schema_name(custom_schema_name, node) -%}
    {%- set default_schema = target.schema -%}

    {%- if target.name == 'prod' and custom_schema_name is not none -%}
        {{ custom_schema_name | trim | upper }}
    {%- elif target.name == 'ci' -%}
        {{ default_schema | trim | upper }}
    {%- elif custom_schema_name is not none -%}
        {{ default_schema | trim | upper }}_{{ custom_schema_name | trim | upper }}
    {%- else -%}
        {{ default_schema | trim | upper }}
    {%- endif -%}
{%- endmacro %}
