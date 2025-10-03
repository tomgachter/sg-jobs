import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import dayjs from 'dayjs';

type JobEvent = {
  id: number;
  title: string;
  start: string;
  end: string;
  status: string;
};

function BoardApp(): JSX.Element {
  const [events, setEvents] = useState<JobEvent[]>([]);

  useEffect(() => {
    fetch(`/wp-json/sgjobs/v1/jobs?date=${dayjs().format('YYYY-MM-DD')}`)
      .then((res) => res.json())
      .then((data) => setEvents(data.jobs || []))
      .catch((err) => console.error('Failed loading jobs', err));
  }, []);

  return (
    <div className="sg-jobs-board">
      <header>
        <h1>Disposition</h1>
        <p>Live-Daten aus bexio Delivery Notes inklusive Blocker-Überlagerung.</p>
      </header>
      <FullCalendar
        plugins={[timeGridPlugin, dayGridPlugin, interactionPlugin]}
        initialView="timeGridWeek"
        events={events}
        slotDuration="00:30:00"
        height="auto"
      />
    </div>
  );
}

const container = document.getElementById('sg-jobs-board');
if (container) {
  createRoot(container).render(<BoardApp />);
}
