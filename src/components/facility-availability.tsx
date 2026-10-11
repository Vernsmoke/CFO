"use client";
import { useState } from "react";
import { manilaDay } from "@/lib/policy";
import { formatTime, Status } from "./ui";
import type { FacilityRequest } from "@/lib/types";
export function FacilityAvailability({
  facilities,
  bookings,
}: {
  facilities: string[];
  bookings: Pick<FacilityRequest, "facility" | "start" | "end" | "status">[];
}) {
  const [facility, setFacility] = useState(facilities[0] ?? "");
  const [date, setDate] = useState(manilaDay());
  const start = Date.parse(`${date}T00:00:00+08:00`);
  const end = start + 86400000;
  const visible = bookings
    .filter(
      (booking) =>
        booking.facility === facility &&
        Date.parse(booking.start) < end &&
        Date.parse(booking.end) > start,
    )
    .sort((a, b) => a.start.localeCompare(b.start));
  return (
    <details className="create-panel">
      <summary>Check availability</summary>
      <div className="availability-content">
        <div className="form-grid">
          <label className="field">
            <span>Availability facility</span>
            <select value={facility} onChange={(event) => setFacility(event.target.value)}>
              {facilities.map((name) => (
                <option key={name}>{name}</option>
              ))}
            </select>
          </label>
          <label className="field">
            <span>Availability date</span>
            <input type="date" value={date} onChange={(event) => setDate(event.target.value)} />
          </label>
        </div>
        <p className="small muted">Philippine time. Pending requests are provisional.</p>
        <div aria-live="polite">
          {visible.length ? (
            visible.map((booking, index) => (
              <div className="availability-row" key={`${booking.start}-${index}`}>
                <strong>
                  {Date.parse(booking.start) < start ? "00:00" : formatTime(booking.start)} –{" "}
                  {Date.parse(booking.end) > end ? "24:00" : formatTime(booking.end)}
                </strong>
                <Status>{booking.status}</Status>
              </div>
            ))
          ) : (
            <p>No pending or approved bookings for this date.</p>
          )}
        </div>
      </div>
    </details>
  );
}
