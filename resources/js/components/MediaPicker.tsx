import React, { useState, useEffect } from 'react';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import MediaLibraryModal from './MediaLibraryModal';
import { Image as ImageIcon, X, FileText, User } from 'lucide-react';
import { getImagePath } from '@/utils/helpers';
import { cn } from '@/lib/utils';
import { useTranslation } from 'react-i18next';

interface MediaPickerProps {
  label?: string;
  value?: string;
  onChange: (value: string) => void;
  multiple?: boolean;
  placeholder?: string;
  showPreview?: boolean;
  readOnly?: boolean;
}

export default function MediaPicker({
  label,
  value = '',
  onChange,
  multiple = false,
  placeholder = 'Select image...',
  showPreview = true,
  readOnly = false
}: MediaPickerProps) {
  const { t } = useTranslation();
  const [isModalOpen, setIsModalOpen] = useState(false);


  //   const handleSelect = (selectedUrl: string) => {
  //   // Convert full URL to path by removing domain
  //   const path = selectedUrl.startsWith('http') ? new URL(selectedUrl).pathname : selectedUrl;
  //   onChange(path);
  // };
  const handleSelect = (selectedUrl: string) => {
    // Extract only the filename from the full path
    const filename = selectedUrl.split('/').pop() || selectedUrl;
    onChange(filename);
  };

  const handleClear = () => {
    onChange('');
  };

  // Ensure value is always a string, never null
  const safeValue = value || '';

  // Process the image URL for preview
  const getDisplayUrl = (url: string) => {
    if (!url) return '';

    // If it's already a full URL, use it as is
    if (url.startsWith('http')) {
      return url;
    }

    // If it starts with /, add the base URL
    if (url.startsWith('/')) {
      return getImagePath(url);
    }
    // Otherwise, prepend /storage/
    return getImagePath(url);
  };

  const imageUrls = safeValue ? [getDisplayUrl(safeValue)] : [];

  const getFileIcon = (url: string) => {
    if (!url) return null;
    const extension = url.split('.').pop()?.toLowerCase();

    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(extension || '')) {
      return null; // Return null for images to show actual image
    }

    if (extension === 'pdf') return <div className="h-16 w-16 bg-red-500 rounded text-white text-xs flex items-center justify-center font-bold">PDF</div>;
    if (['doc', 'docx'].includes(extension || '')) return <div className="h-16 w-16 bg-blue-500 rounded text-white text-xs flex items-center justify-center font-bold">DOC</div>;
    if (['xls', 'xlsx', 'csv'].includes(extension || '')) return <div className="h-16 w-16 bg-green-500 rounded text-white text-xs flex items-center justify-center font-bold">XLS</div>;
    if (['ppt', 'pptx'].includes(extension || '')) return <div className="h-16 w-16 bg-orange-500 rounded text-white text-xs flex items-center justify-center font-bold">PPT</div>;

    return <div className="h-16 w-16 bg-gray-500 rounded text-white text-xs flex items-center justify-center font-bold">FILE</div>;
  };

  return (
    <div className="space-y-4 w-full flex flex-col items-center">
      {label && <Label className="text-sm font-semibold text-slate-700 text-center w-full">{label}</Label>}

      {/* Circular Preview Area */}
      <div 
        className={cn(
          "w-32 h-32 rounded-full border-2 border-dashed border-primary/30 flex items-center justify-center bg-slate-50/50 relative overflow-hidden group transition-all",
          !readOnly && "hover:border-primary/50 cursor-pointer"
        )}
        onClick={() => !readOnly && setIsModalOpen(true)}
      >
        {safeValue ? (
          <img
            src={getDisplayUrl(safeValue)}
            alt="Preview"
            className="w-full h-full object-cover"
            onError={(e) => {
              const target = e.target as HTMLImageElement;
              target.style.display = 'none';
              target.parentElement!.innerHTML = '<div class="flex flex-col items-center text-slate-400"><FileText class="h-10 w-10" /></div>';
            }}
          />
        ) : (
          <div className="flex flex-col items-center text-slate-400">
            <User className="h-12 w-12" />
          </div>
        )}
        
        {!readOnly && (
          <div className="absolute inset-0 bg-black/20 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
            <ImageIcon className="h-6 w-6 text-white" />
          </div>
        )}
      </div>

      {/* Browse Bar */}
      <div className="flex gap-2 w-full max-w-sm">
        <Input
          value={safeValue}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          readOnly={true}
          onClick={() => !readOnly && setIsModalOpen(true)}
          className={cn(
            "h-10 rounded-lg text-xs bg-white cursor-pointer border-slate-200",
            readOnly && "opacity-50 cursor-not-allowed"
          )}
        />
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => setIsModalOpen(true)}
          disabled={readOnly}
          className="h-10 px-4 rounded-lg border-slate-200 hover:bg-slate-50 flex items-center gap-2 font-medium"
        >
          <ImageIcon className="h-4 w-4" />
          {t('Browse')}
        </Button>
        {safeValue && !readOnly && (
          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={handleClear}
            className="h-10 w-10 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg"
          >
            <X className="h-4 w-4" />
          </Button>
        )}
      </div>

      <MediaLibraryModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSelect={handleSelect}
        multiple={multiple}
      />
    </div>
  );
}