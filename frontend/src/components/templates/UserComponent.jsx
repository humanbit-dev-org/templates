import { useEffect, useState } from 'react';

export default function UserComponent() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    // Definisci la tua API URL, può essere un endpoint della tua API Laravel
    const apiUrl = process.env.NEXT_PUBLIC_BACKEND_URL;

    const fetchData = async () => {
      try {
        // const response = await fetch(`${process.env.NEXT_PUBLIC_BACKEND_URL}/api/data`, {
        const response = await fetch(`${apiUrl}/api/data`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            // 'Authorization': `Bearer ${localStorage.getItem('token')}`, // Se hai bisogno di autenticazione
          },
        });

        if (!response.ok) {
          throw new Error('Errore nel fetching dei dati');
        }

        const result = await response.json();
        setData(result);
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []);

  if (loading) {
    return <p>Caricamento...</p>;
  }

  if (error) {
    return <p>Errore: {error}</p>;
  }

  return (
    <div>
      <h1>Risultati API:</h1>
      <pre>{ data.message }</pre>
      {/* <pre>{JSON.stringify(data, null, 2)}</pre> */}
    </div>
  );
}
