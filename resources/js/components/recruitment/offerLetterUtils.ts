import axios from 'axios';

export type OfferTemplateRow = {
    id: number;
    name: string;
    template_content: string;
    variables?: string[];
    status?: string;
};

export function fillOfferTemplate(content: string, variables: Record<string, string>): string {
    let result = content;
    for (const [key, value] of Object.entries(variables)) {
        result = result.replaceAll(`{{${key}}}`, value);
    }
    return result;
}

export function sampleOfferVariables(): Record<string, string> {
    return {
        candidate_name: 'Rajesh Kumar',
        position: 'CNC Machine Operator',
        joining_date: new Date().toLocaleDateString('en-IN'),
        salary: '24,000',
    };
}

export function variablesFromOffer(offer: {
    candidate?: { first_name?: string; last_name?: string };
    position?: string;
    start_date?: string;
    salary?: string | number;
}): Record<string, string> {
    const name = [offer.candidate?.first_name, offer.candidate?.last_name].filter(Boolean).join(' ');
    return {
        candidate_name: name || 'Candidate',
        position: offer.position ?? '',
        joining_date: offer.start_date
            ? new Date(offer.start_date).toLocaleDateString('en-IN')
            : '',
        salary: offer.salary != null ? String(offer.salary) : '',
    };
}

export async function fetchOfferLetterPreview(
    templateId: number,
    variables: Record<string, string>
): Promise<{ content: string; html: string }> {
    const response = await axios.post(route('hr.recruitment.offer-templates.preview', templateId), { variables });
    return response.data;
}

export async function downloadOfferLetterPdf(
    templateId: number,
    variables: Record<string, string>,
    filename: string
): Promise<void> {
    const response = await axios.post(
        route('hr.recruitment.offer-templates.generate', templateId),
        { variables, filename },
        { responseType: 'blob' }
    );

    const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${filename}.pdf`;
    document.body.appendChild(anchor);
    anchor.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(anchor);
}
