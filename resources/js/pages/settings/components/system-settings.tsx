import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { useState, useEffect } from 'react';
import { Save } from 'lucide-react';
import { SettingsSection } from '@/components/settings-section';
import { useTranslation } from 'react-i18next';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';

interface SystemSettingsProps {
  settings?: Record<string, string>;
  timezones?: Record<string, string>;
  dateFormats?: Record<string, string>;
  timeFormats?: Record<string, string>;
  isCompanyUser?: boolean;
}

export default function SystemSettings({
  settings = {},
  timezones = {},
  dateFormats = {},
  timeFormats = {},
  isCompanyUser = false
}: SystemSettingsProps) {
  const { t } = useTranslation();
  const pageProps = usePage().props as any;

  // Default settings
  const defaultSettings = {
    defaultLanguage: 'en',
    dateFormat: 'MM/DD/YYYY',
    timeFormat: '12h',
    calendarStartDay: 'sunday',
    defaultTimezone: 'UTC',
    emailVerification: false,
    landingPageEnabled: true,
    trusted_domains: ''
  };

  // Combine settings from props and page props
  const settingsData = Object.keys(settings).length > 0
    ? settings
    : (pageProps.settings || {});

  // Initialize state with merged settings
  const [timezoneSearch, setTimezoneSearch] = useState('');
  const [systemSettings, setSystemSettings] = useState(() => ({
    defaultLanguage: settingsData.defaultLanguage || defaultSettings.defaultLanguage,
    dateFormat: settingsData.dateFormat || defaultSettings.dateFormat,
    timeFormat: settingsData.timeFormat || defaultSettings.timeFormat,
    calendarStartDay: settingsData.calendarStartDay || defaultSettings.calendarStartDay,
    defaultTimezone: settingsData.defaultTimezone || defaultSettings.defaultTimezone,
    emailVerification: settingsData.emailVerification === 'true' || settingsData.emailVerification === true || defaultSettings.emailVerification,
    landingPageEnabled: settingsData.landingPageEnabled === 'true' || settingsData.landingPageEnabled === true || settingsData.landingPageEnabled === '1' || (settingsData.landingPageEnabled === undefined ? defaultSettings.landingPageEnabled : false),
    trusted_domains: settingsData.trusted_domains || defaultSettings.trusted_domains
  }));

  // Update state when settings change
  useEffect(() => {
    if (Object.keys(settingsData).length > 0) {
      // Create merged settings object
      const mergedSettings = Object.keys(defaultSettings).reduce((acc, key) => {
        const k = key as keyof typeof defaultSettings;
        acc[key] = settingsData[key] !== undefined ? settingsData[key] : defaultSettings[k];
        return acc;
      }, {} as Record<string, any>);

      setSystemSettings(prevSettings => ({
        ...prevSettings,
        ...mergedSettings,
        emailVerification: String(mergedSettings.emailVerification) === 'true' || mergedSettings.emailVerification === true || mergedSettings.emailVerification === '1',
        landingPageEnabled: String(mergedSettings.landingPageEnabled) === 'true' || mergedSettings.landingPageEnabled === true || mergedSettings.landingPageEnabled === '1' || (mergedSettings.landingPageEnabled === undefined ? defaultSettings.landingPageEnabled : false)
      }));
    }
  }, [settingsData]);


  // Handle system settings form changes
  const handleSystemSettingsChange = (field: string, value: string | boolean) => {
    setSystemSettings(prev => ({
      ...prev,
      [field]: value
    }));
  };

  // Handle system settings form submission
  const submitSystemSettings = (e: React.FormEvent) => {
    e.preventDefault();

    // Create clean settings object
    const cleanSettings: Record<string, any> = {
      defaultLanguage: systemSettings.defaultLanguage,
      dateFormat: systemSettings.dateFormat,
      timeFormat: systemSettings.timeFormat,
      calendarStartDay: systemSettings.calendarStartDay,
      defaultTimezone: systemSettings.defaultTimezone,
      emailVerification: Boolean(systemSettings.emailVerification),
      landingPageEnabled: Boolean(systemSettings.landingPageEnabled),
      trusted_domains: systemSettings.trusted_domains
    };

    // Only include email verification and landing page for non-company users
    if (!isCompanyUser) {
      cleanSettings.emailVerification = Boolean(systemSettings.emailVerification);
      cleanSettings.landingPageEnabled = Boolean(systemSettings.landingPageEnabled);
    }

    // Submit to backend using Inertia
    router.post(route('settings.system.update'), cleanSettings, {
      preserveScroll: true,
      onSuccess: (page: any) => {
        const successMessage = page.props.flash?.success;
        const errorMessage = page.props.flash?.error;

        if (successMessage) {
          toast.success(successMessage);
        } else if (errorMessage) {
          toast.error(errorMessage);
        }
      },
      onError: (errors) => {
        const errorMessage = errors.error || Object.values(errors).join(', ') || t('Failed to update system settings');
        toast.error(errorMessage);
      }
    });
  };

  // Filter timezones based on search
  const filteredTimezones = Object.entries(timezones).filter(([timezone, description]) =>
    timezone.toLowerCase().includes(timezoneSearch.toLowerCase()) ||
    description.toLowerCase().includes(timezoneSearch.toLowerCase())
  );

  return (
    <SettingsSection
      title={t("System Settings")}
      description={t("Configure system-wide settings for your application")}
      action={
        <Button type="submit" form="system-settings-form" size="sm">
          <Save className="h-4 w-4 mr-2" />
          {t("Save Changes")}
        </Button>
      }
    >
      <form id="system-settings-form" onSubmit={submitSystemSettings} className="space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">


          <div className="grid gap-2">
            <Label htmlFor="dateFormat">{t("Date Format")}</Label>
            <Select
              value={systemSettings.dateFormat}
              onValueChange={(value) => handleSystemSettingsChange('dateFormat', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder={t("Select date format")} />
              </SelectTrigger>
              <SelectContent>
                {Object.keys(dateFormats).length > 0 ?
                  Object.entries(dateFormats).map(([format, example]) => (
                    <SelectItem key={format} value={format}>
                      <div className="flex items-center justify-between w-full">
                        <span>{format}</span>
                        <span className="text-muted-foreground text-sm ml-4">({example})</span>
                      </div>
                    </SelectItem>
                  )) : (
                    <>
                      <SelectItem value="M j, Y">Jan 1, 2025</SelectItem>
                      <SelectItem value="d-m-Y">01-01-2025</SelectItem>
                      <SelectItem value="Y-m-d">2025-01-01</SelectItem>
                      <SelectItem value="F j, Y">January 1, 2025</SelectItem>
                    </>
                  )
                }
              </SelectContent>
            </Select>
          </div>

          <div className="grid gap-2">
            <Label htmlFor="timeFormat">{t("Time Format")}</Label>
            <Select
              value={systemSettings.timeFormat}
              onValueChange={(value) => handleSystemSettingsChange('timeFormat', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder={t("Select time format")} />
              </SelectTrigger>
              <SelectContent>
                {Object.keys(timeFormats).length > 0 ?
                  Object.entries(timeFormats).map(([format, example]) => (
                    <SelectItem key={format} value={format}>
                      <div className="flex items-center justify-between w-full">
                        <span>{format}</span>
                        <span className="text-muted-foreground text-sm ml-4">({example})</span>
                      </div>
                    </SelectItem>
                  )) : (
                    <>
                      <SelectItem value="g:i A">1:30 PM</SelectItem>
                      <SelectItem value="H:i">13:30</SelectItem>
                      <SelectItem value="g:i a">1:30 pm</SelectItem>
                    </>
                  )
                }
              </SelectContent>
            </Select>
          </div>

          {!isCompanyUser && (
            <>
              <div className="grid gap-2">
                <Label htmlFor="calendarStartDay">{t("Calendar Start Day")}</Label>
                <Select
                  value={systemSettings.calendarStartDay}
                  onValueChange={(value) => handleSystemSettingsChange('calendarStartDay', value)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t("Select start day")} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="sunday">{t("Sunday")}</SelectItem>
                    <SelectItem value="monday">{t("Monday")}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </>)}

          <div className="grid gap-2 md:col-span-2">
            <Label htmlFor="defaultTimezone">{t("Default Timezone")}</Label>
            <Select
              value={systemSettings.defaultTimezone}
              onValueChange={(value) => handleSystemSettingsChange('defaultTimezone', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder={t("Select timezone")} />
              </SelectTrigger>
              <SelectContent>
                <div className="p-2">
                  <Input
                    placeholder={t("Search timezones...")}
                    value={timezoneSearch}
                    onChange={(e) => setTimezoneSearch(e.target.value)}
                    className="mb-2"
                  />
                </div>
                <div className="max-h-60 overflow-y-auto">
                  {Object.keys(timezones).length > 0 ?
                    filteredTimezones.map(([timezone, description]) => (
                      <SelectItem key={timezone} value={timezone}>
                        {description}
                      </SelectItem>
                    )) : (
                      <>
                        <SelectItem value="UTC">UTC</SelectItem>
                        <SelectItem value="America/New_York">Eastern Time (ET)</SelectItem>
                        <SelectItem value="America/Chicago">Central Time (CT)</SelectItem>
                        <SelectItem value="Europe/London">London (GMT)</SelectItem>
                      </>
                    )
                  }
                </div>
              </SelectContent>
            </Select>
          </div>

          {!isCompanyUser && (
            <>
              <div className="grid gap-2 md:col-span-2">
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label htmlFor="emailVerification">{t("Email Verification")}</Label>
                    <p className="text-sm text-muted-foreground">
                      {t("Require users to verify their email addresses")}
                    </p>
                  </div>
                  <Switch
                    id="emailVerification"
                    checked={systemSettings.emailVerification}
                    onCheckedChange={(checked) => handleSystemSettingsChange('emailVerification', checked)}
                  />
                </div>
              </div>

              <div className="grid gap-2 md:col-span-2">
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label htmlFor="landingPageEnabled">{t("Landing Page")}</Label>
                    <p className="text-sm text-muted-foreground">
                      {t("Enable or disable the public landing page")}
                    </p>
                  </div>
                  <Switch
                    id="landingPageEnabled"
                    checked={systemSettings.landingPageEnabled}
                    onCheckedChange={(checked) => handleSystemSettingsChange('landingPageEnabled', checked)}
                  />
                </div>
              </div>

              <div className="grid gap-2 md:col-span-2">
                <Label htmlFor="trusted_domains">{t("Trusted Email Domains")}</Label>
                <div className="space-y-1">
                  <Input
                    id="trusted_domains"
                    placeholder={t("e.g. gmail.com, company.com (leave blank for all)")}
                    value={systemSettings.trusted_domains}
                    onChange={(e) => handleSystemSettingsChange('trusted_domains', e.target.value)}
                  />
                  <p className="text-[10px] text-muted-foreground">
                    {t("Separate multiple domains with commas. If blank, any valid email domain will be accepted.")}
                  </p>
                </div>
              </div>
            </>
          )}
        </div>
      </form>
    </SettingsSection>
  );
}