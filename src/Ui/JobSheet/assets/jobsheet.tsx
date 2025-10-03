import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';

type Job = {
  id: number;
  delivery_note_nr: string;
  customer_name: string;
  phones: string[];
  notes: string;
};

function JobSheet(): JSX.Element {
  const [job, setJob] = useState<Job | null>(null);
  const [comment, setComment] = useState('');
  const [status, setStatus] = useState('');

  useEffect(() => {
    const token = window.location.pathname.split('/').filter(Boolean).pop() || '';
    fetch(`/wp-json/sgjobs/v1/jobs/by-token/${token}`)
      .then((res) => res.json())
      .then((data) => setJob(data));
  }, []);

  const markDone = () => {
    if (!job) {
      return;
    }
    const token = window.location.pathname.split('/').filter(Boolean).pop() || '';
    fetch(`/wp-json/sgjobs/v1/jobs/${job.id}/done`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
      body: JSON.stringify({ comment }),
    })
      .then((res) => res.json())
      .then(() => setStatus('done'));
  };

  if (!job) {
    return <p>Lade Jobdaten ...</p>;
  }

  return (
    <div className="jobsheet">
      <h1>
        ✅ Lieferschein
        {' '}
        <span className="delivery-note-number">{job.delivery_note_nr}</span>
      </h1>
      <h2>{job.customer_name}</h2>
      <div className="phones">
        {job.phones.map((phone) => (
          <a key={phone} href={`tel:${phone}`} className="phone-btn">
            {phone}
          </a>
        ))}
      </div>
      <textarea value={comment} onChange={(e) => setComment(e.target.value)} placeholder="Kommentar oder Seriennummer" />
      <button type="button" onClick={markDone} disabled={status === 'done'}>
        ✅ Auftrag erledigt
      </button>
      {status === 'done' && <p className="status">Danke! Status aktualisiert.</p>}
    </div>
  );
}

if ('serviceWorker' in navigator) {
  navigator.serviceWorker
    .register('/wp-content/plugins/sg-jobs/public/sw.js')
    .catch((error) => console.error('Service worker registration failed', error));
}

const container = document.getElementById('sg-jobs-sheet');
if (container) {
  createRoot(container).render(<JobSheet />);
}
