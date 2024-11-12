'use client'

import axios from '@/lib/axios'
import { useState } from 'react'
import Button from '@/components/Button'
import Input from '@/components/Input'
import InputError from '@/components/InputError'
import Label from '@/components/Label'

export const SendGroup = ({ group }) => {
    const [email, setEmail] = useState("")
    const [response, setResponse] = useState(null)
    const [errors, setErrors] = useState([])

    const csrf = () => axios.get('/sanctum/csrf-cookie')

    const grabData = async ({ setErrors, setResponse, ...props }) => {

        await csrf()

        setErrors([])

        axios
            .post(`/api/group/${group.id}/send-invite`, props)
            .then(response => {
                setResponse(response.data)
                setEmail("");
            })
            .catch(error => {
                if (error.response.status !== 422) throw error
                setErrors(error.response.data.errors)
            })
    }

    const submitForm = event => {
        event.preventDefault()

        grabData({
            email,
            setResponse,
            setErrors,
        })
    }

    return (
        <form className="mt-5" onSubmit={submitForm}>

            <div className="mt-5">
                {/* Name */}
                <div>
                    <Label htmlFor="email">Invita un'utente</Label>

                    <Input
                        id="email"
                        type="email"
                        value={email}
                        className="block mt-3"
                        onChange={event => setEmail(event.target.value)}
                        placeholder="example@ex.com"
                        required
                        autoFocus
                    />

                    <InputError messages={errors.email} className="mt-2" />
                </div>

                {response?.message && (
                    <div className="mt-2 italic" style={{ color: "green" }}>{response.message}</div>
                )}
                {errors.length > 0 && (
                    <div className="mt-2 italic" style={{ color: "red" }}>
                        {errors.map((error, index) => (
                            <div key={index}>{error.message}</div>
                        ))}
                    </div>
                )}
                
                <Button className="mt-4">Spedisci mail</Button>
                
            </div>
        </form>
    )
}

export default SendGroup