import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import dayjs from 'dayjs';

type ApiJobEvent = {
  id: string | number;
  title: string;
  start: string;
  end: string;
  status: string;
};

type JobEvent = {
  id: string;
  title: string;
  start: string;
  end: string;
  status: string;
};

const BoardApp: React.FC = () => {
  const [events, setEvents] = useState<JobEvent[]>([]);

  useEffect(() => {
    const today = dayjs().format('YYYY-MM-DD');
    fetch(`/wp-json/sgjobs/v1/jobs?date=${today}`)
      .then((res) => res.json())
      .then((data) => {
        const parsed: ApiJobEvent[] = Array.isArray(data.jobs) ? data.jobs : [];
        setEvents(
          parsed.map((event) => ({
            ...event,
            id: String(event.id),
          })),
        );
      })
      .catch((error) => console.error('Failed loading jobs', error));
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
};

const container = document.getElementById('sg-jobs-board');
if (container) {
  createRoot(container).render(<BoardApp />);
}
