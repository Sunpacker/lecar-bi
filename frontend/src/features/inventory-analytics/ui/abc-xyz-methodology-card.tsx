'use client'

import React, { useState } from 'react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { ChevronDownIcon, ChevronUpIcon, HelpCircleIcon } from 'lucide-react'

const GROUPS_INFO = [
  {
    group: 'AX',
    title: 'Высокая выручка + стабильный спрос',
    strategy:
      'Just-in-Time, автоматические заказы, минимальный страховой запас. Недопустим Out-of-Stock.',
    badgeClass: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
  },
  {
    group: 'AY',
    title: 'Высокая выручка + колебания спроса',
    strategy:
      'Страховой запас для сглаживания сезонных пиков, регулярный пересмотр прогноза.',
    badgeClass: 'bg-teal-500/20 text-teal-300 border-teal-500/30',
  },
  {
    group: 'AZ',
    title: 'Высокая выручка + нерегулярный спрос',
    strategy:
      'Заказ под клиента или минимальный страховой буфер. Контроль риска заморозки капитала.',
    badgeClass: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
  },
  {
    group: 'BX',
    title: 'Средняя выручка + стабильный спрос',
    strategy:
      'Заказ фиксированными партиями по расписанию, периодический контроль остатков.',
    badgeClass: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30',
  },
  {
    group: 'BY',
    title: 'Средняя выручка + колебания спроса',
    strategy: 'Гибкие партии с учетом сезонности, поддержание умеренного буфера.',
    badgeClass: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
  },
  {
    group: 'BZ',
    title: 'Средняя выручка + нерегулярный спрос',
    strategy: 'Поставка по заявкам клиентов или консолидация заказов поставщикам.',
    badgeClass: 'bg-orange-500/20 text-orange-300 border-orange-500/30',
  },
  {
    group: 'CX',
    title: 'Низкая выручка + стабильный спрос',
    strategy: 'Поставки крупными партиями с редкой периодичностью, упрощенный учет.',
    badgeClass: 'bg-slate-500/20 text-slate-300 border-slate-500/30',
  },
  {
    group: 'CY',
    title: 'Низкая выручка + колебания спроса',
    strategy:
      'Снижение неснижаемого остатка, периодическая ревизия необходимости в каталоге.',
    badgeClass: 'bg-zinc-500/20 text-zinc-300 border-zinc-500/30',
  },
  {
    group: 'CZ',
    title: 'Низкая выручка + нерегулярный спрос',
    strategy:
      'Кандидаты на вывод из ассортимента или работа строго под заказ. Высокий риск неликвидов.',
    badgeClass: 'bg-rose-500/20 text-rose-300 border-rose-500/30',
  },
]

export function AbcXyzMethodologyCard() {
  const [isOpen, setIsOpen] = useState(false)

  return (
    <Card className="border-border/60 bg-card/60 backdrop-blur-xs">
      <CardHeader className="py-3 px-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <HelpCircleIcon className="h-4 w-4 text-emerald-400" />
            <CardTitle className="text-sm font-semibold">
              Методология совмещенного ABC/XYZ анализа
            </CardTitle>
          </div>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setIsOpen(!isOpen)}
            className="h-8 text-xs text-muted-foreground hover:text-foreground"
          >
            {isOpen ? (
              <>
                Скрыть справку <ChevronUpIcon className="ml-1 h-3.5 w-3.5" />
              </>
            ) : (
              <>
                Как читать матрицу <ChevronDownIcon className="ml-1 h-3.5 w-3.5" />
              </>
            )}
          </Button>
        </div>
        <CardDescription className="text-xs text-muted-foreground">
          Классификация по принципу Парето (выручка) и стабильности спроса (коэффициент
          вариации)
        </CardDescription>
      </CardHeader>

      {isOpen && (
        <CardContent className="pt-2 pb-4 px-4 border-t border-border/40 space-y-4 text-xs">
          {/* Rules definition */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="p-3 bg-muted/30 rounded-lg border border-border/40 space-y-2">
              <h4 className="font-semibold text-foreground flex items-center gap-1.5">
                <span className="h-2 w-2 rounded-full bg-emerald-400"></span>
                ABC-анализ (вклад в выручку):
              </h4>
              <ul className="space-y-1 text-muted-foreground">
                <li>
                  <strong className="text-foreground">Класс A (до 80%):</strong> наиболее
                  ценные товары, формирующие львиную долю оборота компании.
                </li>
                <li>
                  <strong className="text-foreground">Класс B (80% &ndash; 95%):</strong>{' '}
                  товары умеренной доходности, поддерживающие стабильный бизнес.
                </li>
                <li>
                  <strong className="text-foreground">Класс C (95% &ndash; 100%):</strong>{' '}
                  наименее ценные товары, длинный хвост каталога.
                </li>
              </ul>
            </div>

            <div className="p-3 bg-muted/30 rounded-lg border border-border/40 space-y-2">
              <h4 className="font-semibold text-foreground flex items-center gap-1.5">
                <span className="h-2 w-2 rounded-full bg-blue-400"></span>
                XYZ-анализ (предсказуемость спроса):
              </h4>
              <ul className="space-y-1 text-muted-foreground">
                <li>
                  <strong className="text-foreground">Класс X (CV &le; 15%):</strong>{' '}
                  стабильный регулярный спрос, высокая точность прогнозирования.
                </li>
                <li>
                  <strong className="text-foreground">
                    Класс Y (15% &lt; CV &le; 35%):
                  </strong>{' '}
                  умеренные колебания, влияние сезонности или акций.
                </li>
                <li>
                  <strong className="text-foreground">Класс Z (CV &gt; 35%):</strong>{' '}
                  нерегулярный, спонтанный спрос, низкая точность прогноза.
                </li>
              </ul>
            </div>
          </div>

          {/* Matrix group strategies */}
          <div>
            <h4 className="font-semibold text-foreground mb-2">
              Стратегии управления запасами по 9 сегментам:
            </h4>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
              {GROUPS_INFO.map((g) => (
                <div
                  key={g.group}
                  className="p-2.5 rounded-lg border border-border/40 bg-background/50 space-y-1"
                >
                  <div className="flex items-center gap-1.5">
                    <Badge
                      variant="outline"
                      className={`font-mono text-[11px] px-1.5 py-0 ${g.badgeClass}`}
                    >
                      {g.group}
                    </Badge>
                    <span className="font-medium text-[11px] text-foreground line-clamp-1">
                      {g.title}
                    </span>
                  </div>
                  <p className="text-[11px] text-muted-foreground leading-snug">
                    {g.strategy}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </CardContent>
      )}
    </Card>
  )
}
