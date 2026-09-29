import { describe, expect, it } from 'vitest';
import { AttributeLabelPipe } from './attribute-label.pipe';

describe('AttributeLabelPipe', () => {
  const pipe = new AttributeLabelPipe();

  it('отображает русские названия SEO-атрибутов', () => {
    expect(pipe.transform('seo title')).toBe('SEO-заголовок');
    expect(pipe.transform('seo h1')).toBe('SEO-заголовок H1');
    expect(pipe.transform('seo description')).toBe('SEO-описание');
  });

  it('не изменяет неизвестный ключ', () => {
    expect(pipe.transform('Размер')).toBe('Размер');
  });
});
