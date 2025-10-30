import React from 'react'
import { Routes, Route } from 'react-router-dom'
import { AuthProvider } from './contexts/AuthContext'
import PrivateRoute from './middleware/PrivateRoute'
import MainLayout from './layouts/MainLayout'
import Dashboard from './pages/Dashboard'
import Clients from './pages/Clients'
import Loans from './pages/Loans'
import Payments from './pages/Payments'
import Analytics from './pages/Analytics'
import Calendar from './pages/Calendar'
import Login from './pages/Login'
import Register from './pages/Register'
import Prueba from './pages/Prueba'
import LoginClient from './pages/LoginClient'

function App() {
  return (
    <AuthProvider>
      <Routes>
        {/* 🔓 Rutas públicas */}
        <Route path="/" element={<Login />} />
        <Route path="register" element={<Register />} />
        <Route path="prueba" element={<Prueba />} />
        <Route path="/login-client" element={<LoginClient />} />

        {/* 🔒 Rutas protegidas */}
        <Route element={<PrivateRoute />}>
          <Route path="/" element={<MainLayout />}>
            <Route path="dashboard" element={<Dashboard />} />
            <Route path="clients" element={<Clients />} />
            <Route path="loans" element={<Loans />} />
            <Route path="payments" element={<Payments />} />
            <Route path="analytics" element={<Analytics />} />
            <Route path="calendar" element={<Calendar />} />
          </Route>
        </Route>
      </Routes>
    </AuthProvider>
  )
}


export default App