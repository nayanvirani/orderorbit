const STATUS = { published: ['success', 'Published'], scheduled: ['info', 'Scheduled'], ended: ['neutral', 'Ended'], paused: ['warning', 'Paused'], archived: ['neutral', 'Archived'] };

/** An experience's status badges (from ExperienceController::row). */
export default function ExperienceStatus({ experience: e }) {
  const [tone, label] = STATUS[e.display_status] || ['neutral', 'Draft'];
  return (
    <>
      <s-badge tone={tone}>{label}</s-badge>
      {e.not_placed && <> <s-badge tone="critical">Not placed</s-badge></>}
      {e.unpublished && <> <s-badge tone="attention">Unpublished changes</s-badge></>}
    </>
  );
}
