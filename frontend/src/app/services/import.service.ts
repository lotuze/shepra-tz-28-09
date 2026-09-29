import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, switchMap, takeWhile, timer } from 'rxjs';
import { ImportJob, ImportStatus, ImportSubmissionResponse } from '../models/import.model';

const TERMINAL_STATUSES: readonly ImportStatus[] = ['completed', 'completed_with_errors', 'failed'];

@Injectable({ providedIn: 'root' })
export class ImportService {
  constructor(private readonly http: HttpClient) {}

  upload(file: File): Observable<ImportSubmissionResponse> {
    const form = new FormData();
    form.append('file', file);

    return this.http.post<ImportSubmissionResponse>('/api/imports', form);
  }

  getStatus(id: number): Observable<ImportJob> {
    return this.http.get<ImportJob>(`/api/imports/${id}`);
  }

  watchStatus(id: number, intervalMs = 1000): Observable<ImportJob> {
    return timer(0, intervalMs).pipe(
      switchMap(() => this.getStatus(id)),
      takeWhile((job) => !TERMINAL_STATUSES.includes(job.status), true),
    );
  }
}
