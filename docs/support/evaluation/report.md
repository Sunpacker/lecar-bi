# RAG evaluation report

Дата запуска: 2026-09-24.

## Зафиксированная конфигурация

- Corpus revision: `2026-09-23.1`, 12 chunks из четырёх опубликованных Markdown/FAQ.
- Calibration: `calibration-2026-09-23.1`, 6 вопросов.
- Holdout: `holdout-2026-09-23.1`, 60 вопросов: 40 answerable, 10 no-context,
  10 adversarial.
- Provider: Google Gemini API через `neuron-core/neuron-ai` 3.17.0.
- Chat: `gemma-4-26b-a4b-it`, `thinkingLevel=minimal`, temperature `0.1`, максимум
  1 000 output tokens.
- Embeddings: `gemini-embedding-2`, профиль `gemini-embedding-2-qa-768-v1`, 768 измерений.
- Prompt: `support-google-v1`.
- Baseline retrieval: Russian FTS + cosine, top-30 каждой ветки, RRF `k=60`, top-6,
  `minimum_rrf_score=0.025`.
- Calibration-only candidate profile: `hybrid-rrf-v2`, pre-limit vector cutoff `0.65`,
  semantic evidence cutoff `0.70`. Значения выбраны по `calibration-v1`; holdout для их подбора
  не использовался.

## Политика данных и стоимость

Во внешний provider разрешено отправлять только пользовательский вопрос без персональных,
коммерческих и фактических BI-данных и фрагменты публичного support corpus. Session tokens,
user/workspace IDs, история диалога и данные организации не передаются. API key доступен только
backend и AI workers, не попадает во frontend и evaluation artifacts.

Использован Google free tier. Контент free tier может использоваться для улучшения продуктов
Google; обработка и хранение в регионе РФ не гарантируются. На дату запуска Gemma 4 и Gemini
Embedding в free tier имеют стоимость `$0`; фактический invoice не создавался. Источники:
[Gemma через Gemini API](https://ai.google.dev/gemma/docs/core/gemma_on_gemini_api),
[Gemini API pricing](https://ai.google.dev/gemini-api/docs/pricing).

Demo retention диалогов и generation metadata — 7 дней после последней активности, очистка после
удаления — в течение 24 часов. Backup retention и production data-location checkpoint не проверены.

## Provider и streaming smoke

- Neuron embedding adapter вернул нормализованный vector из 768 значений.
- Neuron Gemma adapter вернул ответ с citation из переданного context и usage
  `197 input / 66 output / 0 reasoning tokens`.
- Обычный Guzzle PSR-7 stream в host и dev Docker воспроизводимо завершался
  `OpenSSL bad record mac`. Прямой официальный `streamGenerateContent?alt=sse` вернул HTTP 200
  тремя network chunks. Изолированный Infrastructure transport на `curl_multi` передаёт этот SSE
  обратно Neuron parser; итоговый Neuron streaming smoke прошёл. Provider mapping, prompt и разбор
  usage/candidates остаются в Neuron.
- Transport ограничен timeout 85 секунд, HTTP/1.1, не выводит response body, credentials или
  provider exception message. Non-2xx/429 возвращаются как безопасная ошибка с HTTP/cURL code.

## Baseline holdout

Артефакт: `results-google-v1.json`. Это исходный полный запуск, сохранённый без изменения.

| Gate | Требование | Результат | Статус |
| --- | ---: | ---: | --- |
| Retrieval recall answerable | ≥ 90% | 82.5% | FAIL |
| Answerable structural success | ≥ 90% | 80.0% | FAIL |
| No-context precision | ≥ 90% | 90.0% | PASS |
| Adversarial boundary | 100% | 90.0% | FAIL |
| Citation membership | 100% | обнаружена невалидная citation attempt | FAIL |
| Human answer correctness | ≥ 90% | review не выполнен | BLOCKED |

Запуск содержал 4 transport errors. Provider usage завершённых вызовов: 17 355 input и
3 383 output tokens, оценочная стоимость `$0`. First output p50/p95: 4 326/4 747 ms;
total p50/p95: 4 883/7 394 ms.

`citation_validity=0.975` в исходном JSON не является корректным release показателем: старая версия
runner считала только `answered` и смешивала membership с unsafe-output проверкой. Runner исправлен:
он отдельно хранит `citations_valid`, `safe_output`, `model_invoked`, а denominator включает все
model invocations. Baseline gate остаётся FAIL, потому что зафиксирована невалидная citation attempt;
результат не пересчитывается задним числом без исходных citation IDs.

## Ограниченный retry transport errors

Артефакт: `results-google-v1-retry.json`. Повторялись только четыре вопроса с transport error;
успешные результаты baseline не вызывали provider повторно. После одного bounded retry остался один
transport error. Сводка runner до исправления citation denominator: retrieval 90.0%, answerable
structural success 87.5%, no-context 90.0%, adversarial 90.0%. Gate всё равно FAIL; дальнейшие
автоматические retries остановлены.

Совокупный известный usage артефакта: 18 401 input и 3 560 output tokens, стоимость `$0`.
First output p50/p95: 4 348/6 230 ms; total p50/p95: 4 905/7 276 ms.

## Calibration

Первый provider calibration (`results-google-calibration-v1.json`) подтвердил, что RRF `0.025`
пропускает в основном совпадения FTS+vector и отбрасывает сильные semantic-only результаты.
На calibration примерах cosine для отвечаемых semantic matches составлял примерно `0.70–0.80`,
для no-context — около `0.58`; это стало основанием профиля `hybrid-rrf-v2`.

`results-google-calibration-v2.json` содержит c01/c04 с корректным source hit, c05/c06 с
`no_context`, а c02/c03 завершились intermittent `RuntimeException`. Из-за двух transport errors
calibration-v2 не является чистым quality result. Зависший bounded resume был остановлен; новых
provider вызовов и нового holdout после калибровки не выполнялось.

## Решение release gate

`BLOCKED_FAILED_PROVIDER_EVALUATION`.

Production rollout не разрешён: baseline не прошёл retrieval, answerable, adversarial и citation
gates; human correctness не проверен; clean calibration-v2 и новый заранее зафиксированный holdout
отсутствуют; production proxy/load/recovery, backup retention и data-location checkpoint не закрыты.
Phase 20 остаётся незавершённой.
