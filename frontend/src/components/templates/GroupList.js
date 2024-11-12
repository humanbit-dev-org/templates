'use client'

import axios from '@/lib/axios'
import useSWR from 'swr'
import Link from 'next/link'

/**
    * Mostra i gruppi di cui l'utente è un membro e quelli che ha creato.
    *
    * Utilizza SWR per reperire i dati dei gruppi dall'API.
    * Mostra un messaggio di errore se la richiesta fallisce.
    * Creazione di gruppi
    *
    */

export const GroupList = () => {

    // Utilizza SWR per reperire i dati dei gruppi
    const { data: groups, error, isValidating } = useSWR("/api/groups", () =>
        axios
            .get("/api/groups")
            .then(res => res.data)
            .catch(error => {
                if (error.response?.status !== 409) throw error
            }),

        {
            revalidateOnFocus: false, // Disattiva la revalidazione quando si cambia scheda
            revalidateOnReconnect: true, // Attiva la revalidazione alla riconnessione
            revalidateIfStale: true, // Attiva la revalidazione automatica se i dati sono "stale" (obsoleti)
            refreshInterval: 0, // Imposta l'intervallo di refresh automatico a 0 (disattiva completamente)
        }
    );

    // Gestione loading state
    if (isValidating) {
        return <p>Loading groups...</p>;
    }

    // Gestione error state
    if (error) {
        return <p>Error loading groups: {error.message}</p>;
    }

    // Gestione flessibile di array raw o di json in base alla response
    const groupList = Array.isArray(groups) ? groups : groups?.groups || [];
    const createdGroupList = Array.isArray(groups) ? groups : groups?.created_groups || [];

    return (
        <div className="mt-2">
            <h2 style={{ fontWeight: "bold" }}>I tuoi gruppi</h2>

            {/* <pre>{JSON.stringify(createdGroupList, null, 2)}</pre> */}

            {createdGroupList.length > 0 ? (
                <ul className="list-disc list-inside">
                    {createdGroupList.map((group, index) => (
                        <li key={index}>
                            <Link href={`/group/${group.id}`}>{group.name}</Link>
                        </li>
                    ))}
                </ul>
            ) : (
                <p>No groups available.</p>
            )}

            <h2 style={{ fontWeight: "bold" }}>I gruppi di cui fai parte</h2>

            {/* <pre>{JSON.stringify(groupList, null, 2)}</pre> */}

            {groupList.length > 0 ? (
                <ul className="list-disc list-inside">
                    {groupList.map((group, index) => (
                        <li key={index}>
                            <Link href={`/group/${group.id}`}>{group.name}</Link>
                        </li>
                    ))}
                </ul>
            ) : (
                <p>No groups available.</p>
            )}

        </div>
    );
};

export default GroupList