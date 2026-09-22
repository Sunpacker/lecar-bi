<?php

namespace Database\Seeders\Demo;

final class DemoDataCatalog
{
    /** @return list<array{id: string, name: string, slug: string, code: string}> */
    public static function categories(): array
    {
        return [
            ['id' => 'cat-tires-wheels', 'name' => 'Шины и диски', 'slug' => 'tires-and-wheels', 'code' => 'TIRES'],
            ['id' => 'cat-brakes', 'name' => 'Тормозная система', 'slug' => 'braking-systems', 'code' => 'BRAKES'],
            ['id' => 'cat-oils-fluids', 'name' => 'Масла и автохимия', 'slug' => 'oils-and-fluids', 'code' => 'FLUIDS'],
            ['id' => 'cat-filters', 'name' => 'Фильтры', 'slug' => 'filters', 'code' => 'FILTERS'],
            ['id' => 'cat-suspension', 'name' => 'Подвеска и рулевое управление', 'slug' => 'suspension-and-steering', 'code' => 'SUSP'],
            ['id' => 'cat-electrical', 'name' => 'Электрика и освещение', 'slug' => 'electrical-and-lighting', 'code' => 'ELEC'],
            ['id' => 'cat-cooling', 'name' => 'Охлаждение и климат', 'slug' => 'cooling-and-climate', 'code' => 'COOL'],
            ['id' => 'cat-transmission', 'name' => 'Трансмиссия и сцепление', 'slug' => 'transmission-and-clutch', 'code' => 'TRANS'],
        ];
    }

    /** @return list<array{id: string, name: string, country: string}> */
    public static function brands(): array
    {
        return [
            ['id' => 'br-michelin', 'name' => 'Michelin', 'country' => 'Франция'],
            ['id' => 'br-continental', 'name' => 'Continental', 'country' => 'Германия'],
            ['id' => 'br-brembo', 'name' => 'Brembo', 'country' => 'Италия'],
            ['id' => 'br-bosch', 'name' => 'Bosch', 'country' => 'Германия'],
            ['id' => 'br-castrol', 'name' => 'Castrol', 'country' => 'Великобритания'],
            ['id' => 'br-lukoil', 'name' => 'Lukoil', 'country' => 'Россия'],
            ['id' => 'br-mann', 'name' => 'Mann-Filter', 'country' => 'Германия'],
            ['id' => 'br-febi', 'name' => 'Febi Bilstein', 'country' => 'Германия'],
            ['id' => 'br-valeo', 'name' => 'Valeo', 'country' => 'Франция'],
            ['id' => 'br-osram', 'name' => 'Osram', 'country' => 'Германия'],
        ];
    }

    /** @return list<array{id: string, name: string, code: string}> */
    public static function regions(): array
    {
        return [
            ['id' => 'reg-cbr', 'name' => 'Центральный регион (Москва и МО)', 'code' => 'MSK'],
            ['id' => 'reg-nw', 'name' => 'Северо-Западный регион (СПб)', 'code' => 'SPB'],
            ['id' => 'reg-vlg', 'name' => 'Приволжский регион (Самара)', 'code' => 'SAM'],
            ['id' => 'reg-url', 'name' => 'Уральский регион (Екатеринбург)', 'code' => 'EKB'],
            ['id' => 'reg-sib', 'name' => 'Сибирский регион (Новосибирск)', 'code' => 'NSK'],
            ['id' => 'reg-sth', 'name' => 'Южный регион (Краснодар)', 'code' => 'KRD'],
        ];
    }

    /** @return list<array{id: string, region_id: string, name: string, code: string}> */
    public static function warehouses(): array
    {
        return [
            ['id' => 'wh-msk-central', 'region_id' => 'reg-cbr', 'name' => 'Центральный распределительный центр Москва', 'code' => 'WH-MSK-01'],
            ['id' => 'wh-spb-north', 'region_id' => 'reg-nw', 'name' => 'Логистический хаб Санкт-Петербург', 'code' => 'WH-SPB-01'],
            ['id' => 'wh-sam-volga', 'region_id' => 'reg-vlg', 'name' => 'Региональный склад Самара', 'code' => 'WH-SAM-01'],
            ['id' => 'wh-ekb-ural', 'region_id' => 'reg-url', 'name' => 'Уральский распределительный центр', 'code' => 'WH-EKB-01'],
        ];
    }

    /** @return list<array{id: string, name: string, code: string}> */
    public static function salesChannels(): array
    {
        return [
            ['id' => 'ch-b2c-web', 'name' => 'Интернет-магазин B2C', 'code' => 'B2C_WEB'],
            ['id' => 'ch-b2b-portal', 'name' => 'Оптовый портал B2B', 'code' => 'B2B_PORTAL'],
            ['id' => 'ch-mp-ozon', 'name' => 'Маркетплейс Ozon', 'code' => 'MP_OZON'],
            ['id' => 'ch-mp-wb', 'name' => 'Маркетплейс Wildberries', 'code' => 'MP_WB'],
            ['id' => 'ch-retail-store', 'name' => 'Сеть розничных автомагазинов', 'code' => 'RETAIL_STORE'],
        ];
    }

    /** @return list<array{id: string, name: string, lead_time_days: int, reliability_score: float}> */
    public static function suppliers(): array
    {
        return [
            ['id' => 'sup-eurotech', 'name' => 'EuroTech Components Ltd', 'lead_time_days' => 6, 'reliability_score' => 0.96],
            ['id' => 'sup-vostok', 'name' => 'Восток Авто Дистрибьюшн', 'lead_time_days' => 12, 'reliability_score' => 0.88],
            ['id' => 'sup-rusauto', 'name' => 'РусАвто Импорт', 'lead_time_days' => 8, 'reliability_score' => 0.92],
            ['id' => 'sup-global', 'name' => 'Global Auto Supply Direct', 'lead_time_days' => 16, 'reliability_score' => 0.79],
        ];
    }

    /** @return list<array{id: string, category_id: string, brand_id: string, sku: string, name: string, cost_price: float, unit_price: float, seasonal_type: string, abc_class: string}> */
    public static function products(): array
    {
        return [
            // Tires & Wheels
            ['id' => 'prod-conti-wint-16', 'category_id' => 'cat-tires-wheels', 'brand_id' => 'br-continental', 'sku' => 'SKU-TIRE-W16-01', 'name' => 'Шина зимняя шипованная Continental IceContact 3 205/55 R16', 'cost_price' => 5800.00, 'unit_price' => 8900.00, 'seasonal_type' => 'winter_seasonal', 'abc_class' => 'A'],
            ['id' => 'prod-conti-wint-17', 'category_id' => 'cat-tires-wheels', 'brand_id' => 'br-continental', 'sku' => 'SKU-TIRE-W17-02', 'name' => 'Шина зимняя нешипованная Continental VikingContact 7 225/50 R17', 'cost_price' => 7900.00, 'unit_price' => 12400.00, 'seasonal_type' => 'winter_seasonal', 'abc_class' => 'A'],
            ['id' => 'prod-mich-summ-16', 'category_id' => 'cat-tires-wheels', 'brand_id' => 'br-michelin', 'sku' => 'SKU-TIRE-S16-03', 'name' => 'Шина летняя Michelin Primacy 4 205/55 R16', 'cost_price' => 5400.00, 'unit_price' => 8400.00, 'seasonal_type' => 'summer_seasonal', 'abc_class' => 'A'],
            ['id' => 'prod-mich-summ-18', 'category_id' => 'cat-tires-wheels', 'brand_id' => 'br-michelin', 'sku' => 'SKU-TIRE-S18-04', 'name' => 'Шина летняя Michelin Pilot Sport 4 235/45 R18', 'cost_price' => 10200.00, 'unit_price' => 15900.00, 'seasonal_type' => 'summer_seasonal', 'abc_class' => 'B'],

            // Brakes
            ['id' => 'prod-brembo-pad-fr', 'category_id' => 'cat-brakes', 'brand_id' => 'br-brembo', 'sku' => 'SKU-BRK-PAD-01', 'name' => 'Колодки тормозные передние Brembo P85020', 'cost_price' => 1950.00, 'unit_price' => 3200.00, 'seasonal_type' => 'regular', 'abc_class' => 'A'],
            ['id' => 'prod-brembo-disc-fr', 'category_id' => 'cat-brakes', 'brand_id' => 'br-brembo', 'sku' => 'SKU-BRK-DSC-02', 'name' => 'Диск тормозной вентилируемый Brembo 09.9145.11', 'cost_price' => 3100.00, 'unit_price' => 5100.00, 'seasonal_type' => 'regular', 'abc_class' => 'A'],
            ['id' => 'prod-bosch-pad-rr', 'category_id' => 'cat-brakes', 'brand_id' => 'br-bosch', 'sku' => 'SKU-BRK-BOS-03', 'name' => 'Колодки тормозные задние Bosch 0 986 494 004', 'cost_price' => 1450.00, 'unit_price' => 2400.00, 'seasonal_type' => 'regular', 'abc_class' => 'B'],

            // Oils & Fluids
            ['id' => 'prod-cast-edge-5w30', 'category_id' => 'cat-oils-fluids', 'brand_id' => 'br-castrol', 'sku' => 'SKU-OIL-CST-01', 'name' => 'Моторное масло Castrol EDGE 5W-30 LL 4л', 'cost_price' => 3200.00, 'unit_price' => 4950.00, 'seasonal_type' => 'regular', 'abc_class' => 'A'],
            ['id' => 'prod-luk-arm-5w40', 'category_id' => 'cat-oils-fluids', 'brand_id' => 'br-lukoil', 'sku' => 'SKU-OIL-LUK-02', 'name' => 'Моторное масло Lukoil Genesis Armortech 5W-40 4л', 'cost_price' => 1800.00, 'unit_price' => 2950.00, 'seasonal_type' => 'regular', 'abc_class' => 'A'],
            ['id' => 'prod-luk-antifreeze', 'category_id' => 'cat-oils-fluids', 'brand_id' => 'br-lukoil', 'sku' => 'SKU-FLD-ANT-03', 'name' => 'Антифриз Lukoil Red G12 5кг', 'cost_price' => 650.00, 'unit_price' => 1150.00, 'seasonal_type' => 'winter_seasonal', 'abc_class' => 'B'],
            ['id' => 'prod-cast-brake-dot4', 'category_id' => 'cat-oils-fluids', 'brand_id' => 'br-castrol', 'sku' => 'SKU-FLD-DOT-04', 'name' => 'Тормозная жидкость Castrol Brake Fluid DOT4 1л', 'cost_price' => 480.00, 'unit_price' => 850.00, 'seasonal_type' => 'regular', 'abc_class' => 'C'],

            // Filters
            ['id' => 'prod-mann-oil-w712', 'category_id' => 'cat-filters', 'brand_id' => 'br-mann', 'sku' => 'SKU-FLT-OIL-01', 'name' => 'Фильтр масляный Mann-Filter W 712/95', 'cost_price' => 420.00, 'unit_price' => 780.00, 'seasonal_type' => 'regular', 'abc_class' => 'A'],
            ['id' => 'prod-mann-air-c250', 'category_id' => 'cat-filters', 'brand_id' => 'br-mann', 'sku' => 'SKU-FLT-AIR-02', 'name' => 'Фильтр воздушный Mann-Filter C 25 004', 'cost_price' => 680.00, 'unit_price' => 1250.00, 'seasonal_type' => 'summer_seasonal', 'abc_class' => 'B'],
            ['id' => 'prod-mann-cab-cu29', 'category_id' => 'cat-filters', 'brand_id' => 'br-mann', 'sku' => 'SKU-FLT-CAB-03', 'name' => 'Фильтр салонный угольный Mann-Filter CUK 2939', 'cost_price' => 790.00, 'unit_price' => 1450.00, 'seasonal_type' => 'summer_seasonal', 'abc_class' => 'B'],
            ['id' => 'prod-bosch-fuel-01', 'category_id' => 'cat-filters', 'brand_id' => 'br-bosch', 'sku' => 'SKU-FLT-FUL-04', 'name' => 'Фильтр топливный Bosch 0 450 906 457', 'cost_price' => 1100.00, 'unit_price' => 1950.00, 'seasonal_type' => 'regular', 'abc_class' => 'C'],

            // Suspension & Steering
            ['id' => 'prod-febi-lever-fr', 'category_id' => 'cat-suspension', 'brand_id' => 'br-febi', 'sku' => 'SKU-SUS-LVR-01', 'name' => 'Рычаг передней подвески нижний левый Febi Bilstein 39274', 'cost_price' => 3800.00, 'unit_price' => 6200.00, 'seasonal_type' => 'regular', 'abc_class' => 'B'],
            ['id' => 'prod-febi-ball-02', 'category_id' => 'cat-suspension', 'brand_id' => 'br-febi', 'sku' => 'SKU-SUS-BAL-02', 'name' => 'Опора шаровая передняя Febi Bilstein 27421', 'cost_price' => 950.00, 'unit_price' => 1650.00, 'seasonal_type' => 'regular', 'abc_class' => 'C'],
            ['id' => 'prod-febi-rod-03', 'category_id' => 'cat-suspension', 'brand_id' => 'br-febi', 'sku' => 'SKU-SUS-ROD-03', 'name' => 'Стойка стабилизатора передняя Febi Bilstein 21013', 'cost_price' => 750.00, 'unit_price' => 1350.00, 'seasonal_type' => 'regular', 'abc_class' => 'B'],

            // Electrical & Lighting
            ['id' => 'prod-bosch-batt-s4', 'category_id' => 'cat-electrical', 'brand_id' => 'br-bosch', 'sku' => 'SKU-ELC-BAT-01', 'name' => 'Аккумулятор Bosch S4 008 Silver 74Ah 680A', 'cost_price' => 6200.00, 'unit_price' => 9800.00, 'seasonal_type' => 'winter_seasonal', 'abc_class' => 'A'],
            ['id' => 'prod-osram-lamp-h7', 'category_id' => 'cat-electrical', 'brand_id' => 'br-osram', 'sku' => 'SKU-ELC-LMP-02', 'name' => 'Автолампа галогенная Osram Night Breaker Laser H7 (комплект 2 шт.)', 'cost_price' => 1350.00, 'unit_price' => 2450.00, 'seasonal_type' => 'winter_seasonal', 'abc_class' => 'B'],
            ['id' => 'prod-bosch-spark-03', 'category_id' => 'cat-electrical', 'brand_id' => 'br-bosch', 'sku' => 'SKU-ELC-SPK-03', 'name' => 'Свеча зажигания иридиевая Bosch Double Iridium 0 242 240 653', 'cost_price' => 620.00, 'unit_price' => 1100.00, 'seasonal_type' => 'regular', 'abc_class' => 'B'],

            // Cooling & Climate
            ['id' => 'prod-valeo-rad-01', 'category_id' => 'cat-cooling', 'brand_id' => 'br-valeo', 'sku' => 'SKU-COL-RAD-01', 'name' => 'Радиатор охлаждения двигателя Valeo 735284', 'cost_price' => 6100.00, 'unit_price' => 9900.00, 'seasonal_type' => 'summer_seasonal', 'abc_class' => 'C'],
            ['id' => 'prod-valeo-pump-02', 'category_id' => 'cat-cooling', 'brand_id' => 'br-valeo', 'sku' => 'SKU-COL-PMP-02', 'name' => 'Насос водяной (помпа) Valeo 506689', 'cost_price' => 2400.00, 'unit_price' => 3950.00, 'seasonal_type' => 'regular', 'abc_class' => 'C'],

            // Transmission & Clutch
            ['id' => 'prod-valeo-clutch-01', 'category_id' => 'cat-transmission', 'brand_id' => 'br-valeo', 'sku' => 'SKU-TRN-CLT-01', 'name' => 'Комплект сцепления Valeo 826315', 'cost_price' => 8400.00, 'unit_price' => 13700.00, 'seasonal_type' => 'regular', 'abc_class' => 'B'],
        ];
    }
}
