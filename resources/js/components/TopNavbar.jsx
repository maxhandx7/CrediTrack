import React from 'react'
import { useAuth } from '../contexts/AuthContext'

const TopNavbar = () => {
  const { user, logout } = useAuth()

  return (
    <nav className="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
      <div className="container-fluid">
        <div className="d-flex justify-content-between w-100">
          <div className="d-flex align-items-center">
            <h5 className="mb-0 text-dark">Panel de Control</h5>
          </div>
          
          <div className="d-flex align-items-center">
            <div className="dropdown">
              <button
                className="btn btn-outline-secondary dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
              >
                <i className="fas fa-user me-2"></i>
                {user?.name}
              </button>
              <ul className="dropdown-menu dropdown-menu-end">
                <li>
                  <button className="dropdown-item" onClick={logout}>
                    <i className="fas fa-sign-out-alt me-2"></i>
                    Cerrar Sesión
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </nav>
  )
}

export default TopNavbar