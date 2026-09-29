# Combined ABC/XYZ Analysis Methodology

This document records the mathematical formulas, classification algorithms, boundary conditions, and inventory management business strategies for combined ABC/XYZ analysis in the AutoBI platform.

---

## 1. Purpose and Context

Combined ABC/XYZ analysis is intended for category managers, purchasing specialists, and logistics professionals in the automotive industry. It divides catalogs of thousands of spare parts and consumables into 9 manageable segments:

- **ABC analysis** evaluates **each product's contribution to company turnover** (revenue) using the Pareto principle.
- **XYZ analysis** evaluates **demand stability and predictability** for a product using the coefficient of variation of consumption/sales over time.
- **The 3×3 matrix (AX...CZ)** defines a strategy for replenishment, safety stock sizing, and reducing the risk of dead stock.

---

## 2. ABC Analysis Algorithm (by Revenue)

### 2.1. Input Data

For each product $i$ over the selected analytical period ($T \in \{30, 90, 180, 365\}$ days), total sales revenue is calculated:
$$R_i = \sum_{k=1}^{M_i} (\text{quantity}_{i,k} \times \text{unit\_price}_{i,k})$$

Total revenue of the catalog under analysis:
$$R_{\text{total}} = \sum_{i=1}^{N} R_i$$

### 2.2. Share and Sorting

1. Each item's individual share of total revenue is calculated:
   $$\text{share}_i = \begin{cases} \frac{R_i}{R_{\text{total}}}, & \text{if } R_{\text{total}} > 0 \\ 0, & \text{if } R_{\text{total}} = 0 \end{cases}$$
2. All products are sorted by descending revenue ($R_i \downarrow$). Ties use an additional stable sorting criterion (by name or SKU).
3. The cumulative revenue share is calculated as a running total:
   $$\text{cum\_share}_m = \sum_{j=1}^{m} \text{share}_j$$

### 2.3. Class Thresholds

- **Class A (high value):** an item belongs to class A if the previous cumulative share $\text{cum\_share}_{m-1} < 0.80$ (80%). Products in this class generate the first 80% of revenue.
- **Class B (moderate value):** an item belongs to class B if the previous cumulative share falls within $[0.80; 0.95)$ (80%–95%).
- **Class C (low value):** an item belongs to class C if the previous cumulative share is $\ge 0.95$ (95%), or the product had no sales during the period ($R_i = 0$).

---

## 3. XYZ Analysis Algorithm (by Demand Variability)

### 3.1. Time Series Bucketing

The analysis period is divided into equal subperiods (buckets) to eliminate within-week noise and obtain a statistically reliable sample:

- For a 30-day period: 4-5 weekly buckets ($n \ge 4$).
- For a 90-day period: 12-13 weekly buckets ($n \approx 12$).
- For a 180-day period: 6 monthly buckets ($n = 6$).
- For a 365-day period: 12 monthly buckets ($n = 12$).

The vector of product unit sales by time interval is $X = (x_1, x_2, \dots, x_n)$, where $x_j$ is the number of product units sold in interval $j$.

### 3.2. Statistical Formulas

1. **Arithmetic mean demand per interval:**
   $$\bar{x} = \frac{1}{n} \sum_{j=1}^{n} x_j$$

2. **Sample standard deviation:**
   $$s = \sqrt{\frac{1}{n - 1} \sum_{j=1}^{n} (x_j - \bar{x})^2}$$

3. **Coefficient of Variation ($CV$):**
   $$CV = \begin{cases} \frac{s}{\bar{x}} \times 100\%, & \text{if } \bar{x} > 0 \\ \infty, & \text{if } \bar{x} = 0 \end{cases}$$

### 3.3. Class Thresholds

- **Class X ($CV \le 15\%$):** stable, predictable demand with minimal fluctuations. High forecast accuracy.
- **Class Y ($15\% < CV \le 35\%$):** moderate demand variability, pronounced seasonality, promotions, or planned purchasing waves. Medium forecast accuracy.
- **Class Z ($CV > 35\%$ or total sales = 0):** irregular, stochastic demand, infrequent individual sales, and high uncertainty.

---

## 4. Combined Segmentation Matrix (9 Groups)

| Group  |     Segment      | Characteristics                             | Inventory Management Strategy                                                                                              |
| :----: | :--------------: | :------------------------------------------ | :------------------------------------------------------------------------------------------------------------------------- |
| **AX** |   Core stable    | High revenue, predictable demand            | **Just-in-Time**, automatic orders at fixed intervals, minimal safety stock. Stockouts are unacceptable.                   |
| **AY** |  Core seasonal   | High revenue, seasonal fluctuations         | Safety stock to absorb peaks; dynamically revise the reorder point to account for seasonality.                             |
| **AZ** |  Core irregular  | High revenue, infrequent large transactions | Individual monitoring, customer-specific ordering, or a minimal safety buffer. High risk of tying up capital if misjudged. |
| **BX** |  Medium stable   | Moderate revenue, stable demand             | Regular deliveries in standard batches (EOQ), periodic review of replenishment parameters.                                 |
| **BY** | Medium seasonal  | Moderate revenue, fluctuating demand        | Flexible batch sizes based on seasonal coefficients, moderate safety buffer.                                               |
| **BZ** | Medium irregular | Moderate revenue, infrequent demand         | Deliveries against actual requests or consolidated supplier orders. Minimize on-hand inventory.                            |
| **CX** |    Low stable    | Low revenue, steady consumption             | Large, infrequent deliveries, simplified paperwork, and automatic replenishment.                                           |
| **CY** |   Low seasonal   | Low revenue, seasonal fluctuations          | Reduce minimum stock, purchase strictly for the start of the season, review whether maintaining stock is justified.        |
| **CZ** |  Low dead stock  | Low revenue, spontaneous demand             | Candidates for clearance and removal from the assortment. Fulfill only individual customer orders from supplier stock.     |

---

## 5. Boundary and Special Cases

1. **Catalog with no sales during the period ($R_{\text{total}} = 0$):**
   - All products are classified as **CZ** ($\text{share} = 0$, $CV = \text{null}$ or $100\%$).
   - Revenue is $0$; stock levels reflect actual warehouse data.
2. **Product with zero sales when total turnover is nonzero:**
   - Automatically receives class **C** and class **Z** (group **CZ**).
3. **A single time bucket ($n \le 1$):**
   - Standard deviation cannot be calculated using the unbiased variance formula ($n-1$). $s = 0$, $CV = 0\%$, and the item belongs to class **X**.
4. **All buckets have identical nonzero sales ($x_1 = x_2 = \dots = x_n$):**
   - $s = 0$, $CV = 0\%$, and the item belongs to class **X**.
5. **Products with negative or zero cost:**
   - Excluded from inventory valuation or treated as 0 to prevent distortion of working capital.
