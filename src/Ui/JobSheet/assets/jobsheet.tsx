import React, {
  useCallback,
  useEffect,
  useMemo,
  useState,
} from 'react';
import { createRoot } from 'react-dom/client';
import axios, { isAxiosError } from 'axios';
import dayjs, { type Dayjs } from 'dayjs';

type JobPosition = {
  id?: number;
  bexio_position_id?: number;
  article_no?: string;
  title?: string;
  description?: string;
  qty?: number;
  unit?: string;
  work_type?: string;
};

type Job = {
  id: number;
  delivery_note_nr: string;
  customer_name: string;
  phones: string[];
  notes?: string;
  status: string;
  address_line?: string;
  location_city?: string;
  positions?: JobPosition[];
  starts_at?: string;
  ends_at?: string;
  tz?: string;
};

const statusLabels: Record<string, string> = {
  open: '🔴 Offen',
  done: '✅ Erledigt',
  billable: '🧾 Zur Verrechnung',
  paid: '💰 Bezahlt',
};

const parseDate = (value?: string) => {
  if (!value) {
    return null;
  }
  const normalized = value.includes('T') ? value : value.replace(' ', 'T');
  const parsed = dayjs(normalized);
  return parsed.isValid() ? parsed : null;
};

const formatJobTime = (job: Job) => {
  const start = parseDate(job.starts_at);
  const end = parseDate(job.ends_at);
  if (!start || !end) {
    return null;
  }
  const sameDay = start.isSame(end, 'day');
  const formatter = (date: Dayjs) => date.format('DD.MM.YYYY HH:mm');
  return sameDay
    ? `${start.format('DD.MM.YYYY')} · ${start.format('HH:mm')} – ${end.format('HH:mm')} Uhr`
    : `${formatter(start)} – ${formatter(end)} Uhr`;
};

const JobSheet: React.FC = () => {
  const [job, setJob] = useState<Job | null>(null);
  const [comment, setComment] = useState('');
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  const token = useMemo(
    () => window.location.pathname.split('/').filter(Boolean).pop() ?? '',
    [],
  );

  const fetchJob = useCallback(async () => {
    if (!token) {
      setError('Ungültiger Link. Bitte kontaktiere die Dispo.');
      setIsLoading(false);
      return;
    }

    setIsLoading(true);
    setError(null);

    try {
      const response = await axios.get<Job>(
        `/wp-json/sgjobs/v1/jobs/by-token/${encodeURIComponent(token)}`,
      );
      setJob(response.data);
      setStatusMessage(null);
    } catch (requestError) {
      if (isAxiosError(requestError)) {
        const statusText = requestError.response?.status === 404
          ? 'Kein Einsatz zu diesem Link gefunden.'
          : requestError.message;
        setError(statusText);
      } else if (requestError instanceof Error) {
        setError(requestError.message);
      } else {
        setError('Unbekannter Fehler beim Laden des Jobs.');
      }
    } finally {
      setIsLoading(false);
    }
  }, [token]);

  useEffect(() => {
    fetchJob();
  }, [fetchJob]);

  const handleCommentChange = useCallback(
    (event: React.ChangeEvent<HTMLTextAreaElement>) => {
      setComment(event.target.value);
    },
    [],
  );

  const markDone = useCallback(async () => {
    if (!job || !token) {
      return;
    }
    setIsSubmitting(true);
    setError(null);

    try {
      await axios.post(
        `/wp-json/sgjobs/v1/jobs/${job.id}/done`,
        { comment },
        {
          headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`,
          },
        },
      );
      setStatusMessage('Danke! Status aktualisiert.');
      setJob((current) => {
        if (!current) {
          return current;
        }
        return {
          ...current,
          status: 'done',
          notes: comment || current.notes,
        };
      });
      setComment('');
    } catch (requestError) {
      if (isAxiosError(requestError)) {
        setError(requestError.response?.status === 403
          ? 'Aktion nicht erlaubt – bitte Link erneut anfordern.'
          : requestError.message);
      } else if (requestError instanceof Error) {
        setError(requestError.message);
      } else {
        setError('Status konnte nicht aktualisiert werden.');
      }
    } finally {
      setIsSubmitting(false);
    }
  }, [comment, job, token]);

  const canMarkDone = Boolean(job && !['done', 'paid'].includes(job.status));
  const timeRange = job ? formatJobTime(job) : null;

  if (isLoading) {
    return <p className="jobsheet-loading">Lade Einsatzdaten …</p>;
  }

  if (error) {
    return <p className="jobsheet-error" role="alert">{error}</p>;
  }

  if (!job) {
    return <p className="jobsheet-error" role="alert">Keine Daten gefunden.</p>;
  }

  return (
    <div className="jobsheet">
      <header className="jobsheet__header">
        <h1>
          {statusLabels[job.status] ?? '✅ Lieferschein'}
          {' '}
          <span className="jobsheet-number">{job.delivery_note_nr}</span>
        </h1>
        <h2>{job.customer_name}</h2>
        {timeRange && <p className="jobsheet-time">{timeRange}</p>}
        {job.address_line && (
          <p className="jobsheet-address">
            {job.address_line}
            {job.location_city ? ` · ${job.location_city}` : ''}
          </p>
        )}
      </header>

      <section className="jobsheet-section">
        <h3>Kontakt</h3>
        <div className="jobsheet-phones">
          {job.phones.length === 0 && <p>Keine Telefonnummer hinterlegt.</p>}
          {job.phones.map((phone) => (
            <a key={phone} href={`tel:${phone}`} className="phone-btn">
              📞
              {' '}
              {phone}
            </a>
          ))}
        </div>
      </section>

      {job.notes && (
        <section className="jobsheet-section">
          <h3>Notizen</h3>
          <p className="jobsheet-notes">{job.notes}</p>
        </section>
      )}

      {(job.positions ?? []).length > 0 && (
        <section className="jobsheet-section">
          <h3>Positionen</h3>
          <ul className="jobsheet-positions">
            {(job.positions ?? []).map((position, index) => {
              const key = position.bexio_position_id ?? position.id ?? index;
              return (
                <li key={key}>
                  <strong>{position.title ?? 'Position'}</strong>
                  <div className="jobsheet-position-meta">
                    {position.article_no && <span>{position.article_no}</span>}
                    {typeof position.qty === 'number' && (
                      <span>
                        {position.qty}
                        {' '}
                        {position.unit ?? ''}
                      </span>
                    )}
                  </div>
                  {position.description && (
                    <p className="jobsheet-position-description">{position.description}</p>
                  )}
                </li>
              );
            })}
          </ul>
        </section>
      )}

      <section className="jobsheet-section jobsheet-action">
        <label htmlFor="jobsheet-comment">
          Kommentar oder Seriennummer
          <textarea
            id="jobsheet-comment"
            value={comment}
            onChange={handleCommentChange}
            placeholder="Notiz hinzufügen …"
            rows={3}
          />
        </label>
        <button
          type="button"
          onClick={markDone}
          disabled={!canMarkDone || isSubmitting}
        >
          ✅ Auftrag erledigt
        </button>
        {statusMessage && <p className="jobsheet-status" role="status">{statusMessage}</p>}
        {!canMarkDone && (
          <p className="jobsheet-status" role="status">
            Dieser Auftrag wurde bereits abgeschlossen.
          </p>
        )}
      </section>
    </div>
  );
};

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/wp-content/plugins/sg-jobs/public/sw.js').catch(console.error);
}

const container = document.getElementById('sg-jobs-sheet');
if (container) {
  createRoot(container).render(<JobSheet />);
}
