import React, { useState } from 'react';
import { Document, Page, pdfjs } from 'react-pdf';
import { Button } from './Button';

// Import CSS for react-pdf
import 'react-pdf/dist/Page/TextLayer.css';
import 'react-pdf/dist/Page/AnnotationLayer.css';

// Set up PDF.js worker
pdfjs.GlobalWorkerOptions.workerSrc = new URL(
  'pdfjs-dist/build/pdf.worker.min.js',
  import.meta.url,
).toString();

interface PDFViewerProps {
  url: string;
  title?: string;
  className?: string;
}

export const PDFViewer: React.FC<PDFViewerProps> = ({ url, title, className = '' }) => {
  const [numPages, setNumPages] = useState<number | null>(null);
  const [pageNumber, setPageNumber] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [scale, setScale] = useState(1.0);

  const onDocumentLoadSuccess = ({ numPages }: { numPages: number }) => {
    setNumPages(numPages);
    setLoading(false);
    setError(null);
  };

  const onDocumentLoadError = (error: Error) => {
    setError('Failed to load PDF document');
    setLoading(false);
    console.error('PDF load error:', error);
  };

  const goToPrevPage = () => {
    setPageNumber(prev => Math.max(prev - 1, 1));
  };

  const goToNextPage = () => {
    setPageNumber(prev => Math.min(prev + 1, numPages || 1));
  };

  const zoomIn = () => {
    setScale(prev => Math.min(prev + 0.2, 3.0));
  };

  const zoomOut = () => {
    setScale(prev => Math.max(prev - 0.2, 0.5));
  };

  const resetZoom = () => {
    setScale(1.0);
  };

  if (loading) {
    return (
      <div className={`flex items-center justify-center min-h-96 ${className}`}>
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#ca1d26] mx-auto mb-4"></div>
          <p className="text-gray-600">Loading PDF...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className={`flex items-center justify-center min-h-96 ${className}`}>
        <div className="text-center">
          <svg className="mx-auto h-12 w-12 text-red-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
          </svg>
          <h3 className="text-lg font-medium text-gray-900 mb-2">Failed to load PDF</h3>
          <p className="text-gray-500">{error}</p>
        </div>
      </div>
    );
  }

  return (
    <div className={`bg-white rounded-lg shadow-lg ${className}`}>
      {/* Header */}
      <div className="bg-gray-50 px-6 py-4 border-b border-gray-200 rounded-t-lg">
        <div className="flex items-center justify-between">
          <div>
            {title && (
              <h3 className="text-lg font-semibold text-gray-900 mb-1">{title}</h3>
            )}
            <p className="text-sm text-gray-600">
              Page {pageNumber} of {numPages}
            </p>
          </div>

          {/* Zoom Controls */}
          <div className="flex items-center space-x-2">
            <Button variant="outline" size="sm" onClick={zoomOut} disabled={scale <= 0.5}>
              <span className="text-sm">-</span>
            </Button>
            <Button variant="outline" size="sm" onClick={resetZoom}>
              <span className="text-sm">{Math.round(scale * 100)}%</span>
            </Button>
            <Button variant="outline" size="sm" onClick={zoomIn} disabled={scale >= 3.0}>
              <span className="text-sm">+</span>
            </Button>
          </div>
        </div>
      </div>

      {/* PDF Document */}
      <div className="p-6">
        <div className="flex justify-center mb-4">
          <Document
            file={url}
            onLoadSuccess={onDocumentLoadSuccess}
            onLoadError={onDocumentLoadError}
            className="pdf-document"
          >
            <Page
              pageNumber={pageNumber}
              scale={scale}
              className="pdf-page shadow-lg"
              renderTextLayer={false}
              renderAnnotationLayer={false}
            />
          </Document>
        </div>

        {/* Navigation Controls */}
        <div className="flex items-center justify-center space-x-4">
          <Button
            variant="outline"
            onClick={goToPrevPage}
            disabled={pageNumber <= 1}
            className="flex items-center space-x-2"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
            <span>Previous</span>
          </Button>

          <div className="flex items-center space-x-2">
            <span className="text-sm text-gray-600">Go to page:</span>
            <input
              type="number"
              min={1}
              max={numPages || 1}
              value={pageNumber}
              onChange={(e) => {
                const page = parseInt(e.target.value);
                if (page >= 1 && page <= (numPages || 1)) {
                  setPageNumber(page);
                }
              }}
              className="w-16 px-2 py-1 text-center border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-[#ca1d26] focus:border-[#ca1d26]"
            />
          </div>

          <Button
            variant="outline"
            onClick={goToNextPage}
            disabled={pageNumber >= (numPages || 1)}
            className="flex items-center space-x-2"
          >
            <span>Next</span>
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
            </svg>
          </Button>
        </div>
      </div>
    </div>
  );
};

// Add some custom CSS for PDF styling
const pdfStyles = `
.pdf-document {
  display: flex;
  justify-content: center;
}

.pdf-page {
  border-radius: 8px;
  overflow: hidden;
}

.react-pdf__Page__canvas {
  display: block;
  margin: 0 auto;
}

.react-pdf__Page {
  position: relative;
}
`;

// Inject styles
if (typeof document !== 'undefined') {
  const styleElement = document.createElement('style');
  styleElement.textContent = pdfStyles;
  document.head.appendChild(styleElement);
}