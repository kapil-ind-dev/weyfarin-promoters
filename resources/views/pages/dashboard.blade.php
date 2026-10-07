@extends('layouts.app')
@section('content')
<div class="page" id="page-dashboard">
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="hero h-100">
                <small class="text-uppercase fw-semibold opacity-75">Good afternoon</small>
                <h2 class="mb-2">Welcome back, Kapil 👋</h2>
                <p class="mb-3" style="max-width:420px">You have 5 tasks due today and 3 new notifications. Let's make today count.</p>
                <div class="d-flex flex-wrap gap-2"><button class="btn btn-brand btn-sm px-3">View tasks</button><a href="#customize" class="btn btn-outline-brand btn-sm px-3">Customize panel</a></div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="panel">
                <div class="d-flex align-items-center gap-3 mb-3"><span class="avatar" style="width:52px;height:52px;font-size:1.2rem">KS</span>
                    <div>
                        <div class="fw-bold">Kapil Sharma</div><small class="text-secondary">kapil@pearsystem.in</small>
                    </div>
                </div>
                <div class="d-flex justify-content-between small mb-1"><span>Profile completion</span><strong>72%</strong></div>
                <div class="progress mb-3">
                    <div class="progress-bar" style="width:72%"></div>
                </div>
                <span class="badge badge-dark">Pro plan</span> <span class="badge badge-soft">Verified</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="panel d-flex align-items-center gap-3"><span class="stat-ico ico-1"><i class="bi bi-people"></i></span>
                <div>
                    <div class="stat-val">2,480</div><small class="text-secondary">Visitors</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="panel d-flex align-items-center gap-3"><span class="stat-ico ico-2"><i class="bi bi-bag-check"></i></span>
                <div>
                    <div class="stat-val">318</div><small class="text-secondary">Orders</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="panel d-flex align-items-center gap-3"><span class="stat-ico ico-3"><i class="bi bi-currency-rupee"></i></span>
                <div>
                    <div class="stat-val">₹84k</div><small class="text-secondary">Revenue</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="panel d-flex align-items-center gap-3"><span class="stat-ico ico-4"><i class="bi bi-star"></i></span>
                <div>
                    <div class="stat-val">4.8</div><small class="text-secondary">Rating</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0">Weekly activity</h6>
                    <select class="form-select form-select-sm w-auto">
                        <option>This week</option>
                        <option>Last week</option>
                    </select>
                </div>
                <div class="bars">
                    <span style="height:45%"></span><span style="height:70%"></span><span class="hl" style="height:95%"></span><span style="height:60%"></span><span style="height:80%"></span><span style="height:35%"></span><span style="height:55%"></span>
                </div>
                <div class="d-flex justify-content-between small text-secondary mt-2 px-1"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="panel">
                <h6 class="fw-bold mb-2">Recent activity</h6>
                <div class="activity"><span class="avatar" style="background:var(--brand);color:var(--accent)">AR</span>
                    <div class="small"><strong>Aarav</strong> commented on <em>Landing page</em>
                        <div class="text-secondary">2 min ago</div>
                    </div>
                </div>
                <div class="activity"><span class="avatar">PM</span>
                    <div class="small"><strong>Priya</strong> uploaded 3 files<div class="text-secondary">1 hour ago</div>
                    </div>
                </div>
                <div class="activity"><span class="avatar" style="background:var(--brand-100)">RK</span>
                    <div class="small"><strong>Rohit</strong> completed <em>Q3 report</em>
                        <div class="text-secondary">Yesterday</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">Recent orders</h6><a href="#" class="small link-strong">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="small text-secondary">
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>#1042</td>
                                <td>Aarav Mehta</td>
                                <td>06 Oct</td>
                                <td>₹2,400</td>
                                <td><span class="badge badge-dark">Paid</span></td>
                            </tr>
                            <tr>
                                <td>#1041</td>
                                <td>Priya Nair</td>
                                <td>05 Oct</td>
                                <td>₹1,150</td>
                                <td><span class="badge badge-soft">Pending</span></td>
                            </tr>
                            <tr>
                                <td>#1040</td>
                                <td>Rohit Kapoor</td>
                                <td>05 Oct</td>
                                <td>₹5,900</td>
                                <td><span class="badge badge-dark">Paid</span></td>
                            </tr>
                            <tr>
                                <td>#1039</td>
                                <td>Sneha Rao</td>
                                <td>04 Oct</td>
                                <td>₹780</td>
                                <td><span class="badge badge-soft">Refunded</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection