import { useEffect } from 'react';
import { Route, Routes, useNavigate } from 'react-router-dom';
import Dashboard from './pages/Dashboard.jsx';

/** Routes handled by the React app; every other /app page still loads in full. */
const SPA = ['/app', '/app/'];

export default function App() {
  const navigate = useNavigate();

  // App Bridge menu clicks: handle React routes in place, let the rest load normally.
  useEffect(() => {
    const onNavigate = (event) => {
      const href = event.target?.getAttribute?.('href');
      if (!href) return;
      const path = new URL(href, window.location.origin).pathname;
      if (SPA.includes(path)) {
        event.preventDefault?.();
        navigate(path.replace(/^\/app/, '') || '/');
      }
    };
    document.addEventListener('shopify:navigate', onNavigate);
    return () => document.removeEventListener('shopify:navigate', onNavigate);
  }, [navigate]);

  return (
    <Routes>
      <Route path="/" element={<Dashboard />} />
    </Routes>
  );
}
