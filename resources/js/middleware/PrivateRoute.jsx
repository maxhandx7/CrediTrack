import React from 'react'
import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'


const PrivateRoute = () => {
  const { user, loading } = useAuth()

  if (loading) return <div className="text-center mt-5">Cargando...</div>

  return user ? <Outlet /> : <Navigate to="/" replace />
}

export default PrivateRoute
