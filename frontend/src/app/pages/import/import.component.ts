import { AsyncPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { RouterLink } from '@angular/router';
import { catchError, finalize, map, of, shareReplay, startWith, Subject, switchMap } from 'rxjs';
import { ImportJob, ImportStatus } from '../../models/import.model';
import { ImportService } from '../../services/import.service';

interface ImportView { loading: boolean; job: ImportJob | null; error: string | null; }

@Component({
  selector: 'app-import',
  standalone: true,
  imports: [AsyncPipe, MatButtonModule, MatCardModule, MatChipsModule, MatProgressBarModule, MatTableModule, RouterLink],
  templateUrl: './import.component.html',
  styleUrl: './import.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ImportComponent {
  readonly selectedFile = signal<File | null>(null);
  readonly selectionError = signal<string | null>(null);
  readonly busy = signal(false);
  readonly errorColumns = ['severity', 'row', 'externalCode', 'message'];
  private readonly imports = inject(ImportService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly submissions = new Subject<File>();

  readonly viewModel$ = this.submissions.pipe(
    switchMap((file) => this.imports.upload(file).pipe(
      switchMap((submission) => this.imports.watchStatus(submission.id)),
      map((job): ImportView => ({ loading: !this.isTerminal(job.status), job, error: null })),
      catchError((error: unknown) => of<ImportView>({ loading: false, job: null, error: error instanceof Error ? error.message : 'Не удалось выполнить импорт.' })),
      startWith<ImportView>({ loading: true, job: null, error: null }),
      finalize(() => this.busy.set(false)),
    )),
    takeUntilDestroyed(this.destroyRef),
    shareReplay({ bufferSize: 1, refCount: true }),
  );

  chooseFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.item(0) ?? null;
    if (file !== null && !file.name.toLocaleLowerCase().endsWith('.xlsx')) {
      this.selectedFile.set(null);
      this.selectionError.set('Выберите файл с расширением .xlsx.');
      input.value = '';
      return;
    }
    this.selectedFile.set(file);
    this.selectionError.set(null);
  }

  startImport(): void {
    const file = this.selectedFile();
    if (file !== null && !this.busy()) {
      this.busy.set(true);
      this.submissions.next(file);
    }
  }

  isTerminal(status: ImportStatus): boolean {
    return ['completed', 'completed_with_errors', 'failed'].includes(status);
  }

  statusLabel(status: ImportStatus): string {
    return ({ queued: 'В очереди', processing: 'Обрабатывается', completed: 'Завершён', completed_with_errors: 'Завершён с замечаниями', failed: 'Ошибка импорта' } satisfies Record<ImportStatus, string>)[status];
  }
}
