import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { firstValueFrom, of, toArray } from 'rxjs';
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { ImportJob } from '../models/import.model';
import { ImportService } from './import.service';

const job = (status: ImportJob['status']): ImportJob => ({
  id: 7, originalFilename: 'items.xlsx', status, totalRows: 1, processedRows: status === 'processing' ? 0 : 1,
  successfulRows: status === 'processing' ? 0 : 1, failedRows: 0, progress: status === 'processing' ? 0 : 100,
  errors: [], errorsTotal: 0, createdAt: '2026-01-01T00:00:00Z', startedAt: null, finishedAt: null, fatalError: null,
});

describe('ImportService', () => {
  let service: ImportService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [ImportService, provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(ImportService);
    http = TestBed.inject(HttpTestingController);
  });
  afterEach(() => http.verify());

  it('загружает файл и получает статус', () => {
    const file = new File(['xlsx'], 'items.xlsx');
    service.upload(file).subscribe();
    const upload = http.expectOne('/api/imports');
    expect(upload.request.method).toBe('POST');
    expect(upload.request.body).toBeInstanceOf(FormData);
    upload.flush({ id: 7, status: 'queued', statusUrl: '/api/imports/7' });

    service.getStatus(7).subscribe();
    const status = http.expectOne('/api/imports/7');
    expect(status.request.method).toBe('GET');
    status.flush(job('completed'));
  });

  it('останавливает polling на terminal status', async () => {
    const status = vi.spyOn(service, 'getStatus')
      .mockReturnValueOnce(of(job('processing')))
      .mockReturnValueOnce(of(job('completed')));
    const jobs = await firstValueFrom(service.watchStatus(7, 1).pipe(toArray()));
    expect(jobs.map((value) => value.status)).toEqual(['processing', 'completed']);
    expect(status).toHaveBeenCalledTimes(2);
  });
});
