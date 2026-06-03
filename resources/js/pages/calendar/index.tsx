import React, { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { useTranslation } from 'react-i18next';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';

interface CalendarEvent {
  id: string;
  title: string;
  start: string | Date;
  end: string | Date;
  type: 'meeting' | 'holiday' | 'leave' | 'week_off';
  allDay?: boolean;
  color: string;
  status?: string;
}

interface CalendarProps {
  events: any[];
  canManage: boolean;
  employmentType: 'Employee' | 'Labour';
}

export default function CalendarIndex({ events: initialEvents, canManage, employmentType }: CalendarProps) {
  const { t } = useTranslation();
  // Ensure all IDs are strings for FullCalendar
  const events = initialEvents ? initialEvents.map(e => ({ ...e, id: String(e.id) })) : [];

  const [selectedEvent, setSelectedEvent] = useState<CalendarEvent | null>(null);
  const [isDialogOpen, setIsDialogOpen] = useState(false);

  const handleEmploymentTypeChange = (val: string) => {
    router.get(route('calendar.index'), { employment_type: val }, { preserveState: true, preserveScroll: true });
  };

  const pageActions: any[] = [];

  const handleEventClick = (clickInfo: any) => {
    const eventId = clickInfo.event.id;
    const event = events.find(e => e.id.toString() === eventId);

    if (event) {
      setSelectedEvent(event);
      setIsDialogOpen(true);
    }
  };

  return (
    <PageTemplate
      title={t('Calendar')}
      url="/calendar"
      actions={pageActions}
    >
      <div className="bg-white rounded-lg shadow p-6 space-y-6">
        <div className="p-4 bg-primary/5 rounded-lg border border-primary/10">
          <Label className="text-sm font-semibold mb-3 block text-primary uppercase tracking-wider">{t('Select Employment Type')}</Label>
          <RadioGroup
            value={employmentType}
            onValueChange={handleEmploymentTypeChange}
            className="flex space-x-6"
          >
            <div className="flex items-center space-x-2 bg-white dark:bg-gray-900 px-4 py-2 rounded-md shadow-sm border border-gray-200 cursor-pointer hover:border-primary transition-colors">
              <RadioGroupItem value="Employee" id="type-employee" />
              <Label htmlFor="type-employee" className="cursor-pointer font-medium">{t('Employee')}</Label>
            </div>
            <div className="flex items-center space-x-2 bg-white dark:bg-gray-900 px-4 py-2 rounded-md shadow-sm border border-gray-200 cursor-pointer hover:border-primary transition-colors">
              <RadioGroupItem value="Labour" id="type-labour" />
              <Label htmlFor="type-labour" className="cursor-pointer font-medium">{t('Labour')}</Label>
            </div>
          </RadioGroup>
          <p className="text-[11px] text-muted-foreground mt-2 italic">
            {t('Changing the employment type will load the corresponding saved settings.')}
          </p>
        </div>

        <div className="flex justify-between items-center">
          <div className="flex gap-4">
            <div className="flex items-center gap-2 text-sm">
              <div className="w-3 h-3 bg-blue-500 rounded"></div>
              <span>{t('Meetings')}</span>
            </div>
            <div className="flex items-center gap-2 text-sm">
              <div className="w-3 h-3 bg-green-500 rounded"></div>
              <span>{t('Holidays')}</span>
            </div>
            <div className="flex items-center gap-2 text-sm">
              <div className="w-3 h-3 bg-yellow-500 rounded"></div>
              <span>{t('Leaves')}</span>
            </div>
            <div className="flex items-center gap-2 text-sm">
              <div className="w-3 h-3 bg-gray-400 rounded"></div>
              <span>{t('Week Offs')}</span>
            </div>
          </div>
        </div>

        <div style={{ height: '600px' }}>
          <FullCalendar
            plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
            initialView="dayGridMonth"
            headerToolbar={{
              left: 'prev,next today',
              center: 'title',
              right: 'dayGridMonth,timeGridWeek,timeGridDay'
            }}
            events={events}
            height="100%"
            editable={canManage}
            selectable={canManage}
            selectMirror={true}
            dayMaxEvents={true}
            weekends={true}
            eventDisplay="block"
            eventBackgroundColor=""
            eventBorderColor=""
            eventTextColor="white"
            eventClick={handleEventClick}
          />
        </div>
      </div>

      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{selectedEvent?.title}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="flex items-center gap-2">
              <Badge variant="outline" className={`
                ${selectedEvent?.type === 'meeting' ? 'bg-blue-50 text-blue-700' : ''}
                ${selectedEvent?.type === 'holiday' ? 'bg-green-50 text-green-700' : ''}
                ${selectedEvent?.type === 'leave' ? 'bg-yellow-50 text-yellow-700' : ''}
                ${selectedEvent?.type === 'week_off' ? 'bg-gray-100 text-gray-700' : ''}
              `}>
                {selectedEvent?.type === 'meeting' && t('Meeting')}
                {selectedEvent?.type === 'holiday' && t('Holiday')}
                {selectedEvent?.type === 'leave' && t('Leave')}
                {selectedEvent?.type === 'week_off' && t('Week Off')}
              </Badge>
              {selectedEvent?.status && (
                <Badge variant="secondary">{selectedEvent.status}</Badge>
              )}
            </div>
            <div>
              <p className="text-sm text-muted-foreground">{t('Start Date')}</p>
              <p className="font-medium">
                {selectedEvent?.start ? new Date(selectedEvent.start).toLocaleString() : ''}
              </p>
            </div>
            <div>
              <p className="text-sm text-muted-foreground">{t('End Date')}</p>
              <p className="font-medium">
                {selectedEvent?.end ? new Date(selectedEvent.end).toLocaleString() : ''}
              </p>
            </div>
            {selectedEvent?.allDay && (
              <div>
                <Badge variant="outline">{t('All Day Event')}</Badge>
              </div>
            )}
          </div>
        </DialogContent>
      </Dialog>
    </PageTemplate>
  );
}