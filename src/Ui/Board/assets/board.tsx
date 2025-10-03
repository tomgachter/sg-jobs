import React, {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import { createRoot } from 'react-dom/client';
import FullCalendar from '@fullcalendar/react';
import type {
  EventContentArg,
  EventInput,
} from '@fullcalendar/core';
import timeGridPlugin from '@fullcalendar/timegrid';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import axios, { isAxiosError } from 'axios';
import dayjs from 'dayjs';

type ApiJobEvent = {
  id: string | number;
  title: string;
  start: string;
  end: string;
  status?: string;
  address?: string;
  team_id?: number | null;
  team_name?: string;
};

type ApiBlockerEvent = {
  id: string | number;
  title: string;
  start: string;
  end: string;
};

type BoardResponse = {
  jobs?: ApiJobEvent[];
  blockers?: ApiBlockerEvent[];
};

type CalendarJob = EventInput & {
  extendedProps: {
    status: string;
    address?: string;
    team?: string;
  };
};

type CalendarBlocker = EventInput & {
  extendedProps: {
    type: 'blocker';
  };
};

type TeamConfig = {
  id?: number;
  name?: string;
};

type BoardConfig = {
  teams?: TeamConfig[];
  baseUrl?: string;
  version?: string;
};

type StatusStyle = {
  emoji: string;
  background: string;
  border: string;
  text: string;
};

declare global {
  interface Window {
    SGJOBS_BOARD?: BoardConfig;
  }
}

const defaultBoardConfig: BoardConfig = {
  teams: [],
  baseUrl: '',
  version: 'dev',
};

const statusStyles = {
  open: {
    emoji: '🔴',
    background: '#d7ebff',
    border: '#1976d2',
    text: '#0d47a1',
  },
  done: {
    emoji: '✅',
    background: '#e6f4ea',
    border: '#2e7d32',
    text: '#1b5e20',
  },
  billable: {
    emoji: '🧾',
    background: '#fff2db',
    border: '#ed6c02',
    text: '#bf360c',
  },
  paid: {
    emoji: '💰',
    background: '#ede7f6',
    border: '#512da8',
    text: '#311b92',
  },
} satisfies Record<string, StatusStyle>;

const blockerStyle = {
  backgroundColor: '#ffebee',
  borderColor: '#ef5350',
};

type CalendarAction = 'prev' | 'next' | 'today';

const resolveStatusStyle = (status: string): StatusStyle => {
  const key = status as keyof typeof statusStyles;
  return statusStyles[key] ?? statusStyles.open;
};

const BoardApp: React.FC = () => {
  const boardConfig = useMemo(
    () => window.SGJOBS_BOARD ?? defaultBoardConfig,
    [],
  );

  const [jobs, setJobs] = useState<CalendarJob[]>([]);
  const [blockers, setBlockers] = useState<CalendarBlocker[]>([]);
  const [currentDate, setCurrentDate] = useState<string>(dayjs().format('YYYY-MM-DD'));
  const [selectedTeam, setSelectedTeam] = useState<string>('all');
  const [view, setView] = useState<'timeGridWeek' | 'timeGridDay'>('timeGridWeek');
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [lastLoadedAt, setLastLoadedAt] = useState<string | null>(null);
  const calendarRef = useRef<FullCalendar | null>(null);

  const loadEvents = useCallback(async (): Promise<void> => {
    setIsLoading(true);
    setError(null);

    const params: Record<string, string> = {
      date: currentDate,
    };
    if (selectedTeam !== 'all') {
      params.team = selectedTeam;
    }

    try {
      const response = await axios.get<BoardResponse>('/wp-json/sgjobs/v1/jobs', {
        params,
      });
      const responseJobs = Array.isArray(response.data.jobs)
        ? response.data.jobs
        : [];
      const responseBlockers = Array.isArray(response.data.blockers)
        ? response.data.blockers
        : [];

      const teamNames = new Map<number, string>();
      (boardConfig.teams ?? []).forEach((team: TeamConfig) => {
        if (typeof team.id === 'number' && team.name) {
          teamNames.set(team.id, team.name);
        }
      });

      setJobs(
        responseJobs.map((event): CalendarJob => {
          const status = event.status ?? 'open';
          const style = resolveStatusStyle(status);
          const title = event.title || 'Unbenannter Einsatz';
          const teamLabel = event.team_name
            || (event.team_id ? teamNames.get(event.team_id) ?? '' : '');

          return {
            id: String(event.id),
            title,
            start: event.start,
            end: event.end,
            backgroundColor: style.background,
            borderColor: style.border,
            textColor: style.text,
            extendedProps: {
              status,
              address: event.address,
              team: teamLabel,
            },
          };
        }),
      );

      setBlockers(
        responseBlockers.map((event): CalendarBlocker => ({
          id: `blocker-${event.id}`,
          title: event.title,
          start: event.start,
          end: event.end,
          display: 'background',
          backgroundColor: blockerStyle.backgroundColor,
          borderColor: blockerStyle.borderColor,
          extendedProps: {
            type: 'blocker',
          },
        })),
      );

      setLastLoadedAt(dayjs().format('HH:mm'));
    } catch (requestError) {
      if (isAxiosError(requestError)) {
        const statusText = requestError.response?.status === 403
          ? 'Keine Berechtigung für den Dispo-Board-Endpunkt.'
          : requestError.message;
        setError(statusText);
      } else if (requestError instanceof Error) {
        setError(requestError.message);
      } else {
        setError('Unbekannter Fehler beim Laden der Einsätze.');
      }
    } finally {
      setIsLoading(false);
    }
  }, [boardConfig, currentDate, selectedTeam]);

  useEffect(() => {
    loadEvents();
  }, [loadEvents]);

  useEffect(() => {
    const intervalId = window.setInterval(() => {
      loadEvents();
    }, 60000);

    return () => window.clearInterval(intervalId);
  }, [loadEvents]);

  const combinedEvents = useMemo<EventInput[]>(
    () => [...jobs, ...blockers],
    [jobs, blockers],
  );

  const handleTeamChange = useCallback(
    (event: React.ChangeEvent<HTMLSelectElement>) => {
      setSelectedTeam(event.target.value);
    },
    [],
  );

  const handleCalendarAction = useCallback(
    (action: CalendarAction) => {
      const api = calendarRef.current?.getApi();
      if (!api) {
        return;
      }

      if (action === 'today') {
        api.today();
      } else if (action === 'next') {
        api.next();
      } else {
        api.prev();
      }

      setCurrentDate(dayjs(api.getDate()).format('YYYY-MM-DD'));
    },
    [],
  );

  const handleViewChange = useCallback(
    (nextView: 'timeGridWeek' | 'timeGridDay') => {
      setView(nextView);
      calendarRef.current?.getApi().changeView(nextView);
    },
    [],
  );

  const handleDatesSet = useCallback((arg: { start: Date }) => {
    setCurrentDate(dayjs(arg.start).format('YYYY-MM-DD'));
  }, []);

  const eventContent = useCallback(
    (eventInfo: EventContentArg) => {
      const status = (eventInfo.event.extendedProps.status as string) ?? 'open';
      const style = resolveStatusStyle(status);
      const address = eventInfo.event.extendedProps.address as string | undefined;
      const team = eventInfo.event.extendedProps.team as string | undefined;
      const timeText = eventInfo.timeText ? `${eventInfo.timeText} ` : '';

      return (
        <div className="sgjobs-board__event">
          <strong>
            {style.emoji}
            {' '}
            {timeText}
            {eventInfo.event.title}
          </strong>
          {address && <div className="sgjobs-board__event-address">{address}</div>}
          {team && <div className="sgjobs-board__event-team">{team}</div>}
        </div>
      );
    },
    [],
  );

  const eventClassNames = useCallback(
    (arg: EventContentArg) => {
      const status = (arg.event.extendedProps.status as string) ?? 'open';
      return [`sgjobs-board__event--${status}`];
    },
    [],
  );

  const formattedDate = useMemo(
    () => dayjs(currentDate).format('dddd, DD.MM.YYYY'),
    [currentDate],
  );

  const teamOptions = useMemo(
    () => (boardConfig.teams ?? []).filter((team): team is TeamConfig & { id: number } => (
      typeof team.id === 'number'
    )),
    [boardConfig],
  );

  return (
    <div className="sg-jobs-board">
      <header className="sgjobs-board__header">
        <h1>Disposition</h1>
        <p>Live-Daten aus bexio Delivery Notes inklusive Blocker-Überlagerung.</p>
      </header>

      <div className="sgjobs-board__controls">
        <div className="sgjobs-board__controls-left">
          <label htmlFor="sgjobs-board-team">
            Team
            <select
              id="sgjobs-board-team"
              value={selectedTeam}
              onChange={handleTeamChange}
            >
              <option value="all">Alle Teams</option>
              {teamOptions.map((team) => (
                <option key={team.id} value={String(team.id)}>
                  {team.name ?? `Team ${team.id}`}
                </option>
              ))}
            </select>
          </label>
        </div>

        <div className="sgjobs-board__controls-right">
          <button type="button" onClick={() => handleCalendarAction('prev')}>
            ←
          </button>
          <button type="button" onClick={() => handleCalendarAction('today')}>
            Heute
          </button>
          <button type="button" onClick={() => handleCalendarAction('next')}>
            →
          </button>
          <button
            type="button"
            className={view === 'timeGridWeek' ? 'is-active' : ''}
            onClick={() => handleViewChange('timeGridWeek')}
          >
            Woche
          </button>
          <button
            type="button"
            className={view === 'timeGridDay' ? 'is-active' : ''}
            onClick={() => handleViewChange('timeGridDay')}
          >
            Tag
          </button>
          <button type="button" onClick={loadEvents} disabled={isLoading}>
            Neu laden
          </button>
        </div>
      </div>

      <div className="sgjobs-board__status">
        <span>{formattedDate}</span>
        {lastLoadedAt && (
          <span className="sgjobs-board__status-updated">
            Aktualisiert um
            {' '}
            {lastLoadedAt}
            {' '}
            Uhr
          </span>
        )}
        {isLoading && <span className="sgjobs-board__status-loading">Aktualisiere …</span>}
      </div>

      {error && (
        <div className="sgjobs-board__error" role="alert">
          {error}
        </div>
      )}

      <FullCalendar
        ref={calendarRef}
        plugins={[timeGridPlugin, dayGridPlugin, interactionPlugin]}
        initialView={view}
        events={combinedEvents}
        eventClassNames={eventClassNames}
        eventContent={eventContent}
        eventDisplay="block"
        height="auto"
        slotDuration="00:30:00"
        headerToolbar={false}
        nowIndicator
        allDaySlot={false}
        firstDay={1}
        datesSet={handleDatesSet}
      />
    </div>
  );
};

const container = document.getElementById('sg-jobs-board');
if (container) {
  createRoot(container).render(<BoardApp />);
}
