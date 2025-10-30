import React from 'react'
import { Outlet, useNavigate, useLocation } from 'react-router-dom'
import Sidebar from '../components/Sidebar'
import TopNavbar from '../components/TopNavbar'

const MainLayout = () => {
  return (
    <div className="container-fluid">
      <div className="row">
        <Sidebar />
        <div className="col-lg-10 col-md-9 ms-sm-auto px-0">
          <TopNavbar />
          <main className="container-fluid mt-4">
            <Outlet />
          </main>
        </div>
      </div>
    </div>
  )
}

export default MainLayout