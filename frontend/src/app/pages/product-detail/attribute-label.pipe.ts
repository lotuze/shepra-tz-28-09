import { Pipe, PipeTransform } from '@angular/core';

const ATTRIBUTE_LABELS: Readonly<Record<string, string>> = {
  'seo title': 'SEO-заголовок',
  'seo h1': 'SEO-заголовок H1',
  'seo description': 'SEO-описание',
};

@Pipe({ name: 'attributeLabel', standalone: true })
export class AttributeLabelPipe implements PipeTransform {
  transform(key: string): string {
    return ATTRIBUTE_LABELS[key] ?? key;
  }
}
