import React from 'react';
import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Printer } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Department {
  id: number;
  name: string;
}

interface Designation {
  id: number;
  name: string;
  code: string;
  rate: number;
  department?: Department;
  employees_count: number;
}

interface Props {
  designations: Designation[];
  company_name: string;
  print_date: string;
}

export default function DesignationReport({ designations, company_name, print_date }: Props) {
  const { t } = useTranslation();

  const totalEmployees = designations.reduce((sum, d) => sum + (d.employees_count || 0), 0);

  return (
    <div className="min-h-screen bg-white p-4 font-sans text-black">
      <Head title={t('Designation Master Report')} />
      
      {/* Action Bar - Hidden during print */}
      <div className="fixed top-4 right-4 print:hidden flex gap-2">
        <Button onClick={() => window.print()} variant="default" className="bg-blue-600 hover:bg-blue-700 text-white shadow-lg">
          <Printer className="w-4 h-4 mr-2" />
          {t('Print Report')}
        </Button>
      </div>

      <div className="max-w-[1000px] mx-auto border border-black p-8 shadow-sm">
        {/* Header */}
        <div className="text-center border-b-2 border-black pb-4 mb-6">
          <h1 className="text-2xl font-bold uppercase mb-2 text-blue-900">{company_name}</h1>
          <div className="flex justify-between items-end border-t border-black pt-4">
            <div className="text-sm font-medium">
              <span>Print Date: {print_date}</span>
            </div>
            <div className="text-3xl font-black underline uppercase tracking-widest text-gray-800">
              Designation Master
            </div>
            <div className="w-32 text-right text-xs font-bold italic">
               CONFIDENTIAL
            </div>
          </div>
        </div>

        {/* Table */}
        <table className="w-full border-collapse border-2 border-black text-sm">
          <thead>
            <tr className="bg-gray-200 border-b-2 border-black text-left">
              <th className="border-r border-black p-2 w-12 text-center font-bold">#</th>
              <th className="border-r border-black p-2 w-32 font-bold">{t('Short Code')}</th>
              <th className="border-r border-black p-2 font-bold">{t('Designation Name')}</th>
              <th className="border-r border-black p-2 font-bold">{t('Department')}</th>
              <th className="border-r border-black p-2 w-24 text-right font-bold">{t('Rate')}</th>
              <th className="p-2 w-24 text-center font-bold">{t('Employees')}</th>
            </tr>
          </thead>
          <tbody>
            {designations.map((designation, index) => (
              <tr key={designation.id} className="border-b border-black hover:bg-gray-50 transition-colors">
                <td className="border-r border-black p-2 text-center">{index + 1}</td>
                <td className="border-r border-black p-2 font-mono font-medium">{designation.code || '-'}</td>
                <td className="border-r border-black p-2 font-semibold uppercase">{designation.name}</td>
                <td className="border-r border-black p-2 italic">{designation.department?.name || '-'}</td>
                <td className="border-r border-black p-2 text-right">
                  {designation.rate ? Number(designation.rate).toLocaleString('en-IN', { minimumFractionDigits: 2 }) : '-'}
                </td>
                <td className="p-2 text-center font-bold text-blue-600">
                  {designation.employees_count}
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr className="bg-blue-50 border-t-2 border-black font-bold">
              <td colSpan={3} className="border-r border-black p-3 text-right text-blue-800 uppercase">
                {t('Grand Total')}
              </td>
              <td className="border-r border-black p-3 text-blue-800">
                {designations.length} {t('Designations')}
              </td>
              <td className="border-r border-black p-3"></td>
              <td className="p-3 text-center text-xl text-blue-700 bg-blue-100">
                {totalEmployees}
              </td>
            </tr>
          </tfoot>
        </table>

        {/* Footer */}
        <div className="mt-16 flex justify-between items-end px-4">
           <div className="text-xs text-gray-500 italic">
             System Generated Report | KIRAN HRMS
           </div>
           
           <div className="flex flex-col items-center">
             <div className="w-48 border-b border-black mb-1"></div>
             <span className="text-sm font-bold uppercase tracking-tighter">Verified By</span>
           </div>

           <div className="flex flex-col items-center">
             <div className="w-48 border-b border-black mb-1"></div>
             <span className="text-sm font-bold uppercase tracking-tighter">Authorized Signatory</span>
           </div>
        </div>
      </div>

      <style dangerouslySetInnerHTML={{ __html: `
        @media print {
          body { padding: 0; background: white; }
          .max-w-[1000px] { 
            border: none !important; 
            box-shadow: none !important;
            width: 100% !important; 
            max-width: 100% !important; 
            padding: 0 !important; 
          }
          .fixed { display: none !important; }
          @page { margin: 1cm; }
          thead { display: table-header-group; }
          tfoot { display: table-footer-group; }
          tr { page-break-inside: avoid; }
          .bg-blue-50 { background-color: #f0f9ff !important; -webkit-print-color-adjust: exact; }
          .bg-blue-100 { background-color: #dbeafe !important; -webkit-print-color-adjust: exact; }
          .text-blue-700 { color: #1d4ed8 !important; -webkit-print-color-adjust: exact; }
          .text-blue-800 { color: #1e40af !important; -webkit-print-color-adjust: exact; }
        }
        table { border-spacing: 0; }
      ` }} />
    </div>
  );
}
