#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Конвертер реального 1С-экспорта (лист "Выгрузка", формат см. 2026-07-29-Vilis.xlsx)
в нейтральный JSON для импорт-скрипта IBLOCK 19 (import_products.php).

Использование:
    python3 xlsx_to_json.py 2026-07-29-Vilis.xlsx products.json

Источник формата — лист "Соответствие" в том же файле (1С-заголовки -> поля сайта).
Здесь маппинг зашит явно, т.к. лист "Соответствие" — просто человекочитаемая
документация одного и того же соответствия.
"""
import sys
import json
import re
import openpyxl

# 1С-заголовок (лист "Выгрузка") -> внутренний ключ
FIELD_MAP = {
    'Артикул': 'article',
    'Модель': 'model',
    'Название': 'name',
    'Фабрика': 'manufacturer',
    'Бренд': 'brand',
    'Категория': 'category',
    'Коллекция': 'collection',
    'Цена': 'price',
    'Скидка': 'discount',
    'Покрытие': 'coating',
    'Цвет': 'coating_color',
    'Оттенок': 'main_color',
    'Остекление': 'glazing',
    'Кромка': 'edge',
    'Стиль': 'style',
    'Открывание': 'open_type',
    'Вид двери': 'door_type',
    'Конструкция': 'construction',
    'Материал': 'material',
    'Шумоизоляция': 'noise_isolation',
    'Огнестойкость': 'fire_resistance',
    'Гарантия': 'warranty',
    'Размеры': 'sizes',
    'Наличие': 'availability',
    'Срок поставки': 'lead_time',
    'Краткое описание': 'short_desc',
    'Полное описание': 'full_desc',
    'Фото-Big': 'photo_big',
    'Фото-Preview': 'photo_preview',
    'Галеррея': 'gallery',
    'Рейтинг': 'rating',
}

# Поля, которые на сайте хранятся как множественные (через запятую в 1С)
MULTI_FIELDS = {'style', 'open_type', 'material'}

NUM_FIELDS = {'price', 'discount', 'rating'}


def normalize_sizes(value):
    if not value:
        return []
    # 1С отдаёт размеры через кириллическую "х" (U+0445), сайту нужна латинская "x"
    value = value.replace('х', 'x').replace('Х', 'x')
    return [s.strip() for s in value.split(',') if s.strip()]


def normalize_multi(value):
    if value is None:
        return []
    return [s.strip() for s in str(value).split(',') if s.strip()]


def normalize_gallery(value):
    if not value:
        return []
    # в тестовом файле разделитель ";" (отличается от "," у остальных списков)
    parts = re.split(r'[;,]', str(value))
    return [s.strip() for s in parts if s.strip()]


def main():
    if len(sys.argv) != 3:
        print('Использование: xlsx_to_json.py <входной.xlsx> <выходной.json>', file=sys.stderr)
        sys.exit(1)

    src, dst = sys.argv[1], sys.argv[2]
    wb = openpyxl.load_workbook(src, data_only=True)
    ws = wb['Выгрузка']

    rows = list(ws.iter_rows(values_only=True))
    header_row = None
    for r in rows:
        if r and r[0] == 'Артикул':
            header_row = r
            header_idx = rows.index(r)
            break
    if header_row is None:
        print('Не нашёл строку заголовков ("Артикул") на листе "Выгрузка"', file=sys.stderr)
        sys.exit(1)

    headers = [h for h in header_row if h is not None]
    col_index = {h: i for i, h in enumerate(header_row) if h is not None}

    unmapped = [h for h in headers if h not in FIELD_MAP]
    if unmapped:
        print('ВНИМАНИЕ: неизвестные заголовки, пропущены:', unmapped, file=sys.stderr)

    products = []
    for r in rows[header_idx + 1:]:
        if not r or r[col_index.get('Артикул', 0)] in (None, ''):
            continue
        item = {}
        for h, key in FIELD_MAP.items():
            if h not in col_index:
                continue
            raw = r[col_index[h]]
            if key == 'sizes':
                item[key] = normalize_sizes(raw)
            elif key == 'gallery':
                item[key] = normalize_gallery(raw)
            elif key in MULTI_FIELDS:
                item[key] = normalize_multi(raw)
            elif key in NUM_FIELDS:
                item[key] = float(raw) if raw not in (None, '') else 0
            else:
                item[key] = ('' if raw is None else str(raw).strip())
        products.append(item)

    with open(dst, 'w', encoding='utf-8') as f:
        json.dump(products, f, ensure_ascii=False, indent=2)

    print('Готово: {} товар(ов) -> {}'.format(len(products), dst))


if __name__ == '__main__':
    main()
